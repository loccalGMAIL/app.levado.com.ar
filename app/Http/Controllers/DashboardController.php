<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\MarginLevel;
use App\Models\Notification;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\ProductionOrderService;
use App\Services\RecipeCostCalculator;
use App\Services\RecipeExploder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ProductionOrderService $productionOrders,
        private readonly RecipeCostCalculator $calculator,
        private readonly RecipeExploder $exploder,
    ) {}

    /**
     * La tabla paginada trabaja EXCLUSIVAMENTE sobre los caches unit_cost y
     * labor_hours que mantiene RecipeCostPropagator: no carga líneas ni
     * recalcula recetas en PHP, y ordena y pagina en SQL. Costo final por
     * unidad = unit_cost + labor_hours × overhead ÷ rendimiento.
     *
     * Los gráficos (gauge, barras, dona) necesitan el desglose por componente,
     * que el cache no guarda, así que se calculan aparte con RecipeCostCalculator
     * sobre las recetas activas.
     */
    public function index(): View
    {
        $tenant = app(Tenant::class);

        // Reconcilia las alertas de estado (stock, costos, compras) antes de mostrar el feed.
        $this->notifications->syncStateAlerts($tenant);
        $alerts = Notification::where('tenant_id', $tenant->id)
            ->active()
            ->unread()
            ->orderByDesc('created_at')
            ->take(6)
            ->get();
        $alertCount = Notification::where('tenant_id', $tenant->id)->active()->unread()->count();

        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list'))
            ?? $priceLists->firstWhere('is_default', true)
            ?? $priceLists->first()
            ?? $tenant->defaultPriceList();

        $totalFixedCosts = $tenant->totalFixedCosts();
        $productiveHours = $tenant->productive_hours_month ?? 0;
        $overheadPerHour = $tenant->overheadPerHour();
        $overhead = $overheadPerHour ?? 0.0;

        // Expresiones SQL sobre los caches. Se repiten inline en los ORDER BY
        // (con sus bindings) porque MySQL no permite reusar alias del SELECT
        // dentro de expresiones nuevas del SELECT.
        // El precio de venta vive en el artículo elaborado (product_prices), no en la receta.
        // Sólo el artículo base (recipe_quantity null): una presentación (pack) tiene
        // precio propio y compararlo contra el costo de 1 unidad falsearía el margen.
        $priceSql = '(select pp.price from product_prices pp '
            ."inner join products p on p.id = pp.product_id and p.type = 'manufactured' and p.recipe_quantity is null "
            .'where p.recipe_id = recipes.id and pp.price_list_id = ? limit 1)';
        $costSql = '(case when recipes.unit_cost is null or recipes.yield_quantity <= 0 then null '
            .'else recipes.unit_cost + (coalesce(recipes.labor_hours, 0) * ? / recipes.yield_quantity) end)';
        $marginSql = "(case when {$priceSql} is null or {$costSql} is null then null else {$priceSql} - {$costSql} end)";
        // Se multiplica por 100.0 (literal real) ANTES de dividir: SQLite hace
        // división entera si ambos operandos son enteros ((price-cost)/price → 0),
        // y el 100.0 fuerza aritmética real. En MySQL es idéntico.
        $marginPctSql = "(case when {$priceSql} is null or {$costSql} is null or {$priceSql} <= 0 then null "
            ."else ({$priceSql} - {$costSql}) * 100.0 / {$priceSql} end)";

        $priceBindings = [$priceList->id];
        $costBindings = [$overhead];
        // Un binding por cada "?" en el orden en que aparece en cada expresión:
        // margin    = P C | P C          → [p, o, p, o]
        // marginPct = P C P | P C P      → [p, o, p, p, o, p]
        $marginBindings = [$priceList->id, $overhead, $priceList->id, $overhead];
        $marginPctBindings = [$priceList->id, $overhead, $priceList->id, $priceList->id, $overhead, $priceList->id];

        $sortExpressions = [
            'name' => ['recipes.name', []],
            'yield_quantity' => ['recipes.yield_quantity', []],
            'selling_price' => [$priceSql, $priceBindings],
            'cost_per_unit' => [$costSql, $costBindings],
            'margin' => [$marginSql, $marginBindings],
            'margin_pct' => [$marginPctSql, $marginPctBindings],
        ];

        $sort = array_key_exists(request('sort', ''), $sortExpressions) ? request('sort') : 'name';
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';
        [$sortExpr, $sortBindings] = $sortExpressions[$sort];

        $marginFilter = request('margin_filter');
        // Constantes del enum, embebidas como literal entero: bindeadas viajan como
        // texto y SQLite compara real contra texto (siempre falso).
        $highFrom = (int) MarginLevel::High->threshold();
        $lowBelow = (int) MarginLevel::Fair->threshold();

        $recipeRows = $tenant->recipes()
            ->active()
            ->with('manufacturedProduct')
            ->when(request('search'), function ($q, $search) {
                $pattern = $this->containsPattern($search);

                return $q->where('recipes.name', 'like', $pattern);
            })
            ->when($marginFilter === 'high', fn ($q) => $q->whereRaw("({$marginPctSql}) >= {$highFrom}", $marginPctBindings))
            ->when($marginFilter === 'low', fn ($q) => $q->whereRaw("({$marginPctSql}) < {$lowBelow}", $marginPctBindings))
            ->select('recipes.*')
            ->selectRaw("{$priceSql} as dashboard_selling_price", $priceBindings)
            ->orderByRaw("({$sortExpr}) is null", $sortBindings)
            ->orderByRaw("({$sortExpr}) {$dir}", $sortBindings)
            ->orderBy('recipes.name')
            ->paginate($this->perPage())
            ->withQueryString()
            ->through(function ($recipe) use ($overheadPerHour) {
                $sellingPrice = $recipe->dashboard_selling_price !== null
                    ? (float) $recipe->dashboard_selling_price
                    : null;
                $yieldQty = (float) $recipe->yield_quantity;
                $laborHours = (float) ($recipe->labor_hours ?? 0);
                $fixedCost = $overheadPerHour !== null ? $laborHours * $overheadPerHour : 0.0;
                $totalCost = ((float) ($recipe->unit_cost ?? 0)) * $yieldQty + $fixedCost;
                $costPerUnit = ($recipe->unit_cost !== null && $yieldQty > 0)
                    ? (float) $recipe->unit_cost + $fixedCost / $yieldQty
                    : null;

                $margin = null;
                $marginPct = null;
                if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
                    $margin = $sellingPrice - $costPerUnit;
                    $marginPct = ($margin / $sellingPrice) * 100;
                }

                return [
                    'recipe' => $recipe,
                    'product' => $recipe->manufacturedProduct,
                    'total_cost' => $totalCost,
                    'cost_per_unit' => $costPerUnit,
                    'selling_price' => $sellingPrice,
                    'margin' => $margin,
                    'margin_pct' => $marginPct,
                ];
            });

        // Política de precio (manual/margen/recargo) por artículo elaborado, en la
        // lista elegida. Se resuelve después de paginar para no N+1 en el subquery.
        $rowProductIds = $recipeRows->getCollection()->pluck('product.id')->filter();
        $policyMap = ProductPrice::where('price_list_id', $priceList->id)
            ->whereIn('product_id', $rowProductIds)
            ->get()
            ->mapWithKeys(fn (ProductPrice $row) => [$row->product_id => $row->policyPayload()]);
        $recipeRows->getCollection()->transform(fn ($row) => $row + [
            'policy' => ($row['product'] !== null ? ($policyMap[$row['product']->id] ?? null) : null)
                ?? ['type' => 'manual', 'value' => null],
        ]);

        $activeRecipeCount = $tenant->recipes()->active()->count();
        $packagingCount = $tenant->packagings()->active()->count();

        // Para los botones "+ Nuevo pedido" / "⚡ Orden instantánea": mismos
        // modales de production-orders/index.blade.php, mismos datos.
        [$locations, $customers, $products] = $this->productionOrders->destinationAndCatalogData($tenant);

        // Para el modal "+ Nueva compra" (mismo dato que junta PurchaseController::index()).
        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();

        // ── Estadísticas y datos para los gráficos ──────────────────────────
        // Se recorren las recetas activas una vez con sus líneas para obtener el
        // desglose por componente (ingredientes / mano de obra / descartables)
        // que la dona necesita y que el cache no almacena.
        $prices = $tenant->recipes()
            ->join('products', function ($join) {
                $join->on('products.recipe_id', '=', 'recipes.id')
                    ->where('products.type', 'manufactured')
                    ->whereNull('products.recipe_quantity');
            })
            ->join('product_prices', function ($join) use ($priceList) {
                $join->on('product_prices.product_id', '=', 'products.id')->where('product_prices.price_list_id', $priceList->id);
            })
            ->pluck('product_prices.price', 'recipes.id');

        $lineRelations = [
            'ingredientLines.ingredient',
            'productLines.product',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
        ];

        // Todas las recetas (no sólo las activas) en una carga, y cada sub-receta
        // re-vinculada a esa misma instancia: así RecipeExploder recorre el árbol
        // completo sin consultas extra, aunque una sub-receta esté inactiva.
        $allRecipes = $tenant->recipes()->with($lineRelations)->get()->keyBy('id');
        foreach ($allRecipes as $loaded) {
            foreach ($loaded->subrecipeLines as $line) {
                if ($child = $allRecipes->get($line->child_recipe_id)) {
                    $line->setRelation('childRecipe', $child);
                }
            }
        }

        $statRows = $allRecipes
            ->where('active', true)
            ->values()
            ->map(function ($recipe) use ($prices, $overheadPerHour) {
                $costs = $this->calculator->calculate($recipe);
                $fixedCost = $overheadPerHour !== null ? $costs['total_labor_hours'] * $overheadPerHour : 0.0;
                $totalCost = $costs['total_cost'] + $fixedCost;
                $yieldQty = (float) $recipe->yield_quantity;
                $costPerUnit = $yieldQty > 0 ? $totalCost / $yieldQty : null;
                $sellingPrice = isset($prices[$recipe->id]) ? (float) $prices[$recipe->id] : null;

                $marginPct = null;
                if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
                    $marginPct = ($sellingPrice - $costPerUnit) / $sellingPrice * 100;
                }

                // Desglose para la dona con el BOM explotado: el costo de una
                // sub-receta se reparte en sus insumos, descartables y mano de
                // obra reales en vez de caer entero en "descartables".
                $exploded = $this->exploder->explodeWithLabor($recipe, 1.0);
                [$packagingItems, $ingredientItems] = $exploded['items']
                    ->partition(fn (array $entry) => $entry['type'] === CatalogItemType::Packaging);
                $lineCost = fn (array $entry) => $entry['quantity'] * (float) $entry['item']->cost_per_unit;

                return [
                    'recipe' => $recipe,
                    'ingredient_cost' => $ingredientItems->sum($lineCost),
                    'packaging_cost' => $packagingItems->sum($lineCost),
                    'labor_cost' => $exploded['labor_cost'],
                    'fixed_cost' => $fixedCost,
                    'total_cost' => $totalCost,
                    'margin_pct' => $marginPct,
                ];
            });

        $rowsWithMargin = $statRows->filter(fn ($r) => $r['margin_pct'] !== null);
        $avgMarginPct = $rowsWithMargin->count() > 0 ? $rowsWithMargin->avg('margin_pct') : null;
        $bestRecipeRow = $rowsWithMargin->sortByDesc('margin_pct')->first();
        $lowMarginCount = $rowsWithMargin->filter(fn ($r) => MarginLevel::for($r['margin_pct']) === MarginLevel::Low)->count();

        // Distribución de costos para la dona: [ingredientes, mano de obra,
        // gastos fijos, descartables]. Los descartables absorben sólo el resto
        // del redondeo, para que la suma cierre en 100%.
        $grandTotalCost = $statRows->sum('total_cost');
        if ($grandTotalCost > 0) {
            $ingredientPct = (int) round($statRows->sum('ingredient_cost') / $grandTotalCost * 100);
            $laborPct = (int) round($statRows->sum('labor_cost') / $grandTotalCost * 100);
            $fixedAllocPct = (int) round($statRows->sum('fixed_cost') / $grandTotalCost * 100);
            $packagingPct = max(0, 100 - $ingredientPct - $laborPct - $fixedAllocPct);
        } else {
            $ingredientPct = $laborPct = $fixedAllocPct = $packagingPct = 0;
        }
        $costDistributionForChart = [$ingredientPct, $laborPct, $fixedAllocPct, $packagingPct];

        // Top 8 recetas por margen para el bar chart.
        $topRecipesForChart = $rowsWithMargin
            ->sortByDesc('margin_pct')
            ->take(8)
            ->values()
            ->map(fn ($r) => [
                'name' => mb_strimwidth($r['recipe']->name, 0, 24, '…'),
                'margin_pct' => (float) number_format((float) $r['margin_pct'], 1, '.', ''),
            ])
            ->toArray();

        return view('dashboard', compact(
            'alerts',
            'alertCount',
            'recipeRows',
            'priceList',
            'priceLists',
            'totalFixedCosts',
            'productiveHours',
            'overheadPerHour',
            'activeRecipeCount',
            'packagingCount',
            'avgMarginPct',
            'bestRecipeRow',
            'lowMarginCount',
            'costDistributionForChart',
            'topRecipesForChart',
            'locations',
            'customers',
            'products',
            'suppliers',
        ));
    }
}

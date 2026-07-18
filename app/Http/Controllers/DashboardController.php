<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * El dashboard trabaja EXCLUSIVAMENTE sobre los caches unit_cost y
     * labor_hours que mantiene RecipeCostPropagator: no carga líneas ni
     * recalcula recetas en PHP, y ordena y pagina en SQL. Costo final por
     * unidad = unit_cost + labor_hours × overhead ÷ rendimiento.
     */
    public function index(): View
    {
        $tenant = app(Tenant::class);

        $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list'))
            ?? $priceLists->firstWhere('is_default', true);

        $totalFixedCosts = $tenant->totalFixedCosts();
        $productiveHours = $tenant->productive_hours_month ?? 0;
        $overheadPerHour = $tenant->overheadPerHour();
        $overhead = $overheadPerHour ?? 0.0;

        // Expresiones SQL sobre los caches. Se repiten inline en los ORDER BY
        // (con sus bindings) porque MySQL no permite reusar alias del SELECT
        // dentro de expresiones nuevas del SELECT.
        $priceSql = '(select rp.price from recipe_prices rp where rp.recipe_id = recipes.id and rp.price_list_id = ?)';
        $costSql = '(case when recipes.unit_cost is null or recipes.yield_quantity <= 0 then null '
            .'else recipes.unit_cost + (coalesce(recipes.labor_hours, 0) * ? / recipes.yield_quantity) end)';
        $marginSql = "(case when {$priceSql} is null or {$costSql} is null then null else {$priceSql} - {$costSql} end)";
        $marginPctSql = "(case when {$priceSql} is null or {$costSql} is null or {$priceSql} <= 0 then null "
            ."else ({$priceSql} - {$costSql}) / {$priceSql} * 100 end)";

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

        $recipeRows = $tenant->recipes()
            ->active()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('recipes.name', 'like', "%{$escaped}%");
            })
            ->select('recipes.*')
            ->selectRaw("{$priceSql} as dashboard_selling_price", $priceBindings)
            ->orderByRaw("({$sortExpr}) is null", $sortBindings)
            ->orderByRaw("({$sortExpr}) {$dir}", $sortBindings)
            ->orderBy('recipes.name')
            ->paginate(20)
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
                    'total_cost' => $totalCost,
                    'cost_per_unit' => $costPerUnit,
                    'selling_price' => $sellingPrice,
                    'margin' => $margin,
                    'margin_pct' => $marginPct,
                ];
            });

        $activeRecipeCount = $tenant->recipes()->active()->count();
        $packagingCount = $tenant->packagings()->active()->count();

        return view('dashboard', compact(
            'recipeRows',
            'priceList',
            'priceLists',
            'totalFixedCosts',
            'productiveHours',
            'overheadPerHour',
            'activeRecipeCount',
            'packagingCount',
        ));
    }
}

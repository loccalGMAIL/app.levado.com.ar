<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ArticlePriceRecalculator;
use App\Services\ProductCodeAssigner;
use App\Services\RecipeCostPropagator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly ProductCodeAssigner $codeAssigner,
        private readonly ArticlePriceRecalculator $priceRecalculator,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        // Costo/precio/margen por artículo: costo total con overhead (fullCost) y precio
        // del artículo en la lista elegida. El precio vive en product_prices (fuente única
        // del artículo) y se edita inline desde el catálogo. La lista se resuelve antes
        // del listado porque también se puede ordenar por su precio.
        $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list')) ?? $priceLists->firstWhere('is_default', true);

        $products = $tenant->products()
            ->with(['recipe', 'category', 'latestCostLog'])
            ->when(request('search'), function ($q, $search) {
                $pattern = $this->containsPattern($search);

                return $q->where(function ($sub) use ($pattern) {
                    $sub->where('name', 'like', $pattern)
                        ->orWhere('sku', 'like', $pattern)
                        ->orWhere('barcode', 'like', $pattern);
                });
            })
            // Los combos tienen su propia pestaña (ProductComboController).
            ->stockable()
            ->when(request('type') === ProductType::Manufactured->value, fn ($q) => $q->where('type', ProductType::Manufactured->value))
            ->when(request('type') === ProductType::Resale->value, fn ($q) => $q->where('type', ProductType::Resale->value))
            // 'sin' es un valor especial (no un id): aísla lo que hoy cuela el gate
            // producible invertido por default, para clasificarlo en pocas pasadas.
            ->when(request('category') === 'sin', fn ($q) => $q->whereNull('product_category_id'))
            ->when(request('category') && request('category') !== 'sin', fn ($q) => $q->where('product_category_id', request('category')))
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                // La columna «Código» muestra el código de barras y, si no hay, el SKU.
                'code' => DB::raw('COALESCE(products.barcode, products.sku)'),
                'name' => 'name',
                'type' => 'type',
                'category' => ProductCategory::select('name')->whereColumn('product_categories.id', 'products.product_category_id'),
                'unit' => 'unit',
                'price' => ProductPrice::select('price')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->where('product_prices.price_list_id', $priceList->id),
            ], fn (Builder $q) => $q->orderBy('name'), 'products.id'))
            ->paginate($this->perPage())
            ->withQueryString();

        // Todas, no sólo las activas: el modal de edición debe poder mostrar una receta
        // ya dada de baja o el select caería en vacío y guardar la perdería en silencio.
        $recipes = $tenant->recipes()->orderBy('name')->get();
        $categories = $tenant->productCategories()->orderBy('name')->get();
        $orderSheets = $tenant->orderSheets()->ordered()->get();
        $showCategories = session('reopen_categories', false);

        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;
        $priceRows = ProductPrice::where('price_list_id', $priceList->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->get();
        $priceMap = $priceRows->pluck('price', 'product_id');
        $policyMap = $priceRows->mapWithKeys(fn (ProductPrice $row) => [$row->product_id => $row->policyPayload()]);

        return view('products.index', compact('products', 'recipes', 'categories', 'orderSheets', 'showCategories', 'priceLists', 'priceList', 'overheadPerHour', 'priceMap', 'policyMap'));
    }

    /**
     * Matriz de precios artículo × lista: la vista comparativa de todas las listas
     * para cada artículo (elaborado y de reventa), dentro de Artículos. Edita el
     * mismo `product_prices` que el catálogo, vía el editor `priceCell`.
     */
    public function matrix(): View
    {
        $tenant = app(Tenant::class);
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $defaultList = $priceLists->firstWhere('is_default', true);
        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $products = $tenant->products()
            ->with(['recipe', 'category', 'comboLines.component.recipe'])
            ->active()
            ->when(request('search'), function ($q, $search) {
                $pattern = $this->containsPattern($search);

                return $q->where(function ($sub) use ($pattern) {
                    $sub->where('name', 'like', $pattern)
                        ->orWhere('sku', 'like', $pattern)
                        ->orWhere('barcode', 'like', $pattern);
                });
            })
            ->when(ProductType::tryFrom((string) request('type')), fn ($q, ProductType $type) => $q->where('type', $type->value))
            ->when(request('category'), fn ($q, $category) => $q->where('product_category_id', $category))
            ->orderBy('name', $dir)
            ->paginate($this->perPage())
            ->withQueryString();

        $productIds = collect($products->items())->pluck('id');

        $costsPerUnit = collect($products->items())
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->fullCost($overheadPerHour)]);

        $priceRows = ProductPrice::whereIn('product_id', $productIds)
            ->whereIn('price_list_id', $priceLists->pluck('id'))
            ->get()
            ->groupBy('product_id');
        $prices = $priceRows->map(fn ($group) => $group->pluck('price', 'price_list_id'));
        $policies = $priceRows->map(fn ($group) => $group->mapWithKeys(fn (ProductPrice $row) => [$row->price_list_id => $row->policyPayload()]));

        $categories = $tenant->productCategories()->orderBy('name')->get();

        return view('products.matrix', compact('products', 'priceLists', 'defaultList', 'costsPerUnit', 'prices', 'policies', 'categories', 'overheadPerHour'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $this->normalizeByType($request->validated());

        $product = $tenant->products()->create($data);
        $this->codeAssigner->assignIfMissing($product);
        $this->logManualCost($product);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'product',
            targetId: $product->id,
            action: 'product.created',
            payload: ['name' => $product->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('products.index'))->with('status', 'Producto creado.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($product->isCombo(), 404);

        // Un elaborado no puede ser línea de receta: pasar a elaborado un artículo
        // usado como insumo dejaría esas recetas apuntando a algo sin costo propio.
        if ($product->isResale() && $request->validated('type') === ProductType::Manufactured->value) {
            $usedIn = $product->recipeNamesUsingAsIngredient();

            if ($usedIn !== []) {
                return back()->withErrors([
                    'type' => 'No podés pasar a elaborado un artículo que se usa como insumo en: '
                        .implode(', ', $usedIn).'.',
                ])->withInput();
            }
        }

        if ($request->validated('unit') !== $product->unit->value && $this->unitIsLocked($product)) {
            return back()->withErrors([
                'unit' => 'No podés cambiar la unidad de un artículo que ya tiene recetas, combos, compras o movimientos de stock: '
                    .'sus cantidades y su costo quedarían en la unidad anterior.',
            ])->withInput();
        }

        $product->update($this->normalizeByType($request->validated()));

        // Se lee antes de assignIfMissing(): ese save() del código de barras
        // reemplaza el changeset y wasChanged() dejaría de ver el costo.
        // `type` y `recipe_id` entran porque normalizeByType() nulea cost_per_unit
        // al pasar a elaborado: el costo cambia de origen aunque la columna no.
        $costChanged = $product->wasChanged(['cost_per_unit', 'type', 'recipe_id', 'recipe_quantity']);
        $costValueChanged = $product->wasChanged('cost_per_unit');

        $this->codeAssigner->assignIfMissing($product);

        if ($costValueChanged) {
            $this->logManualCost($product);
        }

        // Todo otro camino que mueve el costo ya recomputa los precios con política
        // (Compras, el propagador de recetas, gastos fijos). La edición a mano era
        // el único que no, y dejaba las celdas de margen/recargo desactualizadas.
        if ($costChanged) {
            // refresh() recarga también las relaciones ya cargadas: si cambió
            // recipe_id, `recipe` en memoria todavía apunta a la anterior.
            $this->priceRecalculator->recompute($product->refresh());
        }

        // Un artículo de reventa puede ser insumo de recetas: su costo nuevo se propaga.
        if ($costValueChanged && $product->isResale()) {
            $this->propagator->propagateFromProduct($product->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'product',
            targetId: $product->id,
            action: 'product.updated',
            payload: ['name' => $product->name],
            tenantId: $product->tenant_id,
        );

        return back(fallback: route('products.index'))->with('status', 'Producto actualizado.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        // Mismo guard que IngredientController::toggleActive(): las recetas activas
        // seguirían costeando y consumiendo stock de un artículo dado de baja.
        if ($product->active) {
            $blockedBy = $product->recipeNamesUsingAsIngredient(onlyActive: true);

            if ($blockedBy !== []) {
                return back()->withErrors([
                    'toggle' => 'No podés desactivar este artículo porque lo usan como insumo: '
                        .implode(', ', $blockedBy).'. Quitalo de esas recetas primero.',
                ]);
            }
        }

        if ($product->active) {
            $combos = $product->comboNamesUsingAsComponent(onlyActive: true);

            if ($combos !== []) {
                return back()->withErrors([
                    'toggle' => 'No podés desactivar este artículo porque forma parte de: '
                        .implode(', ', $combos).'. Quitalo de esos combos primero.',
                ]);
            }
        } elseif ($product->isCombo()) {
            $inactive = $product->comboLines()
                ->join('products as components', 'components.id', '=', 'product_combo_lines.product_id')
                ->where('components.active', false)
                ->orderBy('components.name')
                ->pluck('components.name')
                ->all();

            if ($inactive !== []) {
                return back()->withErrors([
                    'toggle' => 'No podés activar el combo: tiene componentes desactivados ('.implode(', ', $inactive).').',
                ]);
            }
        }

        $product->update(['active' => ! $product->active]);
        $action = $product->active ? 'product.activated' : 'product.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'product',
            targetId: $product->id,
            action: $action,
            payload: ['name' => $product->name],
            tenantId: $product->tenant_id,
        );

        $label = $product->active ? 'activado' : 'desactivado';

        return back()->with('status', "Producto {$label}.");
    }

    /**
     * Mismo criterio que IngredientController::unitIsLocked().
     */
    private function unitIsLocked(Product $product): bool
    {
        return $product->recipeLines()->exists()
            || $product->comboMemberships()->exists()
            || $product->stockMovements()->exists()
            || DB::table('purchase_lines')
                ->where('purchaseable_type', CatalogItemType::Product->value)
                ->where('purchaseable_id', $product->id)
                ->exists();
    }

    /**
     * Deja rastro de un costo tipeado a mano. Sólo para reventa: el costo del
     * elaborado vive en la receta y su historial se reconstruye desde los logs
     * de precio de los insumos.
     */
    private function logManualCost(Product $product): void
    {
        if (! $product->isResale() || $product->cost_per_unit === null) {
            return;
        }

        $product->costLogs()->create([
            'tenant_id' => $product->tenant_id,
            'cost_per_unit' => $product->cost_per_unit,
            'source' => CostLogSource::Manual,
            'recorded_at' => now(),
        ]);
    }

    /**
     * Fuerza la coherencia de los campos dependientes del tipo: un elaborado no lleva
     * costo propio (se deriva de la receta) y uno de reventa no lleva receta.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeByType(array $data): array
    {
        if (($data['type'] ?? null) === ProductType::Manufactured->value) {
            $data['cost_per_unit'] = null;
            $data['costing_method'] = null;
            // Cantidad 1 = artículo base (el que se produce), no una presentación.
            if (isset($data['recipe_quantity']) && (float) $data['recipe_quantity'] === 1.0) {
                $data['recipe_quantity'] = null;
            }
        } else {
            $data['recipe_id'] = null;
            $data['recipe_quantity'] = null;
        }

        return $data;
    }
}

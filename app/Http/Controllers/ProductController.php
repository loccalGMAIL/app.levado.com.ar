<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ArticlePriceRecalculator;
use App\Services\ProductCodeAssigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly ProductCodeAssigner $codeAssigner,
        private readonly ArticlePriceRecalculator $priceRecalculator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $products = $tenant->products()
            ->with(['recipe', 'category'])
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where(function ($sub) use ($escaped) {
                    $sub->where('name', 'like', "%{$escaped}%")
                        ->orWhere('sku', 'like', "%{$escaped}%")
                        ->orWhere('barcode', 'like', "%{$escaped}%");
                });
            })
            ->when(request('type') === ProductType::Manufactured->value, fn ($q) => $q->where('type', ProductType::Manufactured->value))
            ->when(request('type') === ProductType::Resale->value, fn ($q) => $q->where('type', ProductType::Resale->value))
            ->when(request('category'), fn ($q, $category) => $q->where('product_category_id', $category))
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        // Todas, no sólo las activas: el modal de edición debe poder mostrar una receta
        // ya dada de baja o el select caería en vacío y guardar la perdería en silencio.
        $recipes = $tenant->recipes()->orderBy('name')->get();
        $categories = $tenant->productCategories()->orderBy('name')->get();
        $showCategories = session('reopen_categories', false);

        // Costo/precio/margen por artículo: costo total con overhead (fullCost) y precio
        // del artículo en la lista elegida. El precio vive en product_prices (fuente única
        // del artículo) y se edita inline desde el catálogo.
        $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list')) ?? $priceLists->firstWhere('is_default', true);
        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;
        $priceRows = ProductPrice::where('price_list_id', $priceList->id)
            ->whereIn('product_id', $products->pluck('id'))
            ->get();
        $priceMap = $priceRows->pluck('price', 'product_id');
        $policyMap = $priceRows->mapWithKeys(fn (ProductPrice $row) => [$row->product_id => $row->policyPayload()]);

        return view('products.index', compact('products', 'recipes', 'categories', 'showCategories', 'priceLists', 'priceList', 'overheadPerHour', 'priceMap', 'policyMap'));
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
            ->with(['recipe', 'category'])
            ->active()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where(function ($sub) use ($escaped) {
                    $sub->where('name', 'like', "%{$escaped}%")
                        ->orWhere('sku', 'like', "%{$escaped}%")
                        ->orWhere('barcode', 'like', "%{$escaped}%");
                });
            })
            ->when(request('type') === ProductType::Manufactured->value, fn ($q) => $q->where('type', ProductType::Manufactured->value))
            ->when(request('type') === ProductType::Resale->value, fn ($q) => $q->where('type', ProductType::Resale->value))
            ->when(request('category'), fn ($q, $category) => $q->where('product_category_id', $category))
            ->orderBy('name', $dir)
            ->paginate(30)
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

        $product->update($this->normalizeByType($request->validated()));

        // Se lee antes de assignIfMissing(): ese save() del código de barras
        // reemplaza el changeset y wasChanged() dejaría de ver el costo.
        // `type` y `recipe_id` entran porque normalizeByType() nulea cost_per_unit
        // al pasar a elaborado: el costo cambia de origen aunque la columna no.
        $costChanged = $product->wasChanged(['cost_per_unit', 'type', 'recipe_id']);

        $this->codeAssigner->assignIfMissing($product);

        // Todo otro camino que mueve el costo ya recomputa los precios con política
        // (Compras, el propagador de recetas, gastos fijos). La edición a mano era
        // el único que no, y dejaba las celdas de margen/recargo desactualizadas.
        if ($costChanged) {
            // refresh() recarga también las relaciones ya cargadas: si cambió
            // recipe_id, `recipe` en memoria todavía apunta a la anterior.
            $this->priceRecalculator->recompute($product->refresh());
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
        } else {
            $data['recipe_id'] = null;
        }

        return $data;
    }
}

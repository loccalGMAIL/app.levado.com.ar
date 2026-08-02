<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

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

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $this->normalizeByType($request->validated());

        $product = $tenant->products()->create($data);

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

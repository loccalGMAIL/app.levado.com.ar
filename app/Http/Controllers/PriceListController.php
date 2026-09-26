<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductPriceWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly ProductPriceWriter $writer,
    ) {}

    public function index(): View
    {
        $sortable = ['name', 'adjustment_pct'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $tenant = app(Tenant::class);
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()
            ->withCount('prices')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('is_default')->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return view('price-lists.index', compact('priceLists'));
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $priceList = $tenant->priceLists()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: 'price_list.created',
            payload: ['name' => $priceList->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('price-lists.index'))->with('status', 'Lista de precios creada.');
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $this->authorize('update', $priceList);

        $data = $request->validated();

        if ($priceList->is_default) {
            $data['adjustment_pct'] = null;
        }

        $priceList->update($data);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: 'price_list.updated',
            payload: ['name' => $priceList->name],
            tenantId: $priceList->tenant_id,
        );

        return back(fallback: route('price-lists.index'))->with('status', 'Lista de precios actualizada.');
    }

    public function toggleActive(PriceList $priceList): RedirectResponse
    {
        $this->authorize('update', $priceList);

        if ($priceList->is_default) {
            return back()->withErrors(['toggle' => 'No podés desactivar la lista base.']);
        }

        $priceList->update(['active' => ! $priceList->active]);
        $action = $priceList->active ? 'price_list.activated' : 'price_list.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: $action,
            payload: ['name' => $priceList->name],
            tenantId: $priceList->tenant_id,
        );

        $label = $priceList->active ? 'activada' : 'desactivada';

        return back()->with('status', "Lista de precios {$label}.");
    }

    public function applySuggestions(PriceList $priceList): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $this->authorize('update', $priceList);
        abort_if($priceList->is_default || $priceList->adjustment_pct === null, 422, 'Esta lista no admite sugerencias.');

        $defaultList = $tenant->defaultPriceList();
        $products = $this->sellableManufacturedProducts($tenant);
        $productIds = $products->pluck('id');
        $basePrices = ProductPrice::where('price_list_id', $defaultList->id)->whereIn('product_id', $productIds)->pluck('price', 'product_id');
        $existing = ProductPrice::where('price_list_id', $priceList->id)->whereIn('product_id', $productIds)->pluck('price', 'product_id');

        $applied = DB::transaction(function () use ($products, $existing, $basePrices, $priceList): int {
            $count = 0;
            foreach ($products as $product) {
                if ($existing->has($product->id) || ! $basePrices->has($product->id)) {
                    continue;
                }
                $suggested = round((float) $basePrices->get($product->id) * (1 + (float) $priceList->adjustment_pct / 100), 2);
                $this->writer->set($product, $priceList, $suggested);
                $count++;
            }

            return $count;
        });

        return back(fallback: route('price-lists.index'))
            ->with('status', "Se aplicaron {$applied} sugerencia(s) en la lista \"{$priceList->name}\".");
    }

    public function applyAllSuggestions(): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $lists = $tenant->priceLists()->active()->where('is_default', false)->whereNotNull('adjustment_pct')->get();
        $defaultList = $tenant->defaultPriceList();
        $products = $this->sellableManufacturedProducts($tenant);
        $productIds = $products->pluck('id');
        $basePrices = ProductPrice::where('price_list_id', $defaultList->id)->whereIn('product_id', $productIds)->pluck('price', 'product_id');

        $applied = DB::transaction(function () use ($lists, $products, $productIds, $basePrices): int {
            $count = 0;
            foreach ($lists as $list) {
                $existing = ProductPrice::where('price_list_id', $list->id)->whereIn('product_id', $productIds)->pluck('price', 'product_id');
                foreach ($products as $product) {
                    if ($existing->has($product->id) || ! $basePrices->has($product->id)) {
                        continue;
                    }
                    $suggested = round((float) $basePrices->get($product->id) * (1 + (float) $list->adjustment_pct / 100), 2);
                    $this->writer->set($product, $list, $suggested);
                    $count++;
                }
            }

            return $count;
        });

        return back(fallback: route('products.matrix'))
            ->with('status', "Se aplicaron {$applied} sugerencia(s) en todas las listas.");
    }

    /**
     * Artículos elaborados vendibles (de recetas finales activas). El precio de
     * venta y las sugerencias por % operan sobre estos productos.
     *
     * @return Collection<int, Product>
     */
    private function sellableManufacturedProducts(Tenant $tenant): Collection
    {
        return $tenant->products()
            ->where('type', ProductType::Manufactured->value)
            ->whereHas('recipe', fn ($q) => $q->where('active', true)->where('is_semi_elaborate', false))
            ->get();
    }
}

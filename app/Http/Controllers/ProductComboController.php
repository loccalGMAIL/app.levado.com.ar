<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Http\Requests\SaveProductComboRequest;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ArticlePriceRecalculator;
use App\Services\ComboExploder;
use App\Services\ProductCodeAssigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Combos: artículos que agrupan a otros bajo un código y un precio propios.
 * El precio se edita con el mismo endpoint que cualquier artículo
 * (products.prices.update) y la baja con products.toggle-active.
 */
class ProductComboController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly ProductCodeAssigner $codeAssigner,
        private readonly ArticlePriceRecalculator $priceRecalculator,
        private readonly ComboExploder $exploder,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list')) ?? $priceLists->firstWhere('is_default', true);

        $combos = $tenant->products()
            ->where('type', ProductType::Combo->value)
            ->with(['category', 'comboLines.component.recipe'])
            ->when(request('search'), function ($q, $search) {
                $pattern = $this->containsPattern($search);

                return $q->where(function ($sub) use ($pattern) {
                    $sub->where('name', 'like', $pattern)->orWhere('barcode', 'like', $pattern);
                });
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('name')
            ->paginate($this->perPage())
            ->withQueryString();

        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;
        $priceRows = ProductPrice::where('price_list_id', $priceList->id)
            ->whereIn('product_id', $combos->pluck('id'))
            ->get();
        $priceMap = $priceRows->pluck('price', 'product_id');
        $policyMap = $priceRows->mapWithKeys(fn (ProductPrice $row) => [$row->product_id => $row->policyPayload()]);

        $availability = $this->exploder->availability(collect($combos->items()), $tenant->defaultLocation());

        // Lo que se puede poner en un combo: cualquier artículo activo que no sea otro combo.
        $components = $tenant->products()->active()->stockable()->with('recipe')->orderBy('name')->get();
        $categories = $tenant->productCategories()->orderBy('name')->get();

        return view('products.combos', compact('combos', 'priceLists', 'priceList', 'overheadPerHour', 'priceMap', 'policyMap', 'availability', 'components', 'categories'));
    }

    public function store(SaveProductComboRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        $combo = DB::transaction(function () use ($tenant, $data) {
            $combo = $tenant->products()->create([
                'name' => $data['name'],
                'type' => ProductType::Combo->value,
                'unit' => Unit::Unidad->value,
                'product_category_id' => $data['product_category_id'] ?? null,
                'barcode' => $data['barcode'] ?? null,
            ]);
            $combo->comboLines()->createMany($data['lines']);

            return $combo;
        });

        $this->codeAssigner->assignIfMissing($combo);
        $this->record($request, $combo, 'product.created');

        return back(fallback: route('products.combos.index'))->with('status', 'Combo creado.');
    }

    public function update(SaveProductComboRequest $request, Product $combo): RedirectResponse
    {
        $this->authorize('update', $combo);
        abort_unless($combo->isCombo(), 404);

        $data = $request->validated();

        DB::transaction(function () use ($combo, $data) {
            $combo->update([
                'name' => $data['name'],
                'product_category_id' => $data['product_category_id'] ?? null,
                'barcode' => $data['barcode'] ?? null,
            ]);
            $combo->comboLines()->delete();
            $combo->comboLines()->createMany($data['lines']);
        });

        $this->codeAssigner->assignIfMissing($combo);
        // Cambiar los componentes cambia el costo: las celdas con margen o recargo se recalculan.
        $this->priceRecalculator->recompute($combo->refresh());
        $this->record($request, $combo, 'product.updated');

        return back(fallback: route('products.combos.index'))->with('status', 'Combo actualizado.');
    }

    private function record(SaveProductComboRequest $request, Product $combo, string $action): void
    {
        $this->recorder->record(
            actor: $request->user(),
            targetType: 'product',
            targetId: $combo->id,
            action: $action,
            payload: ['name' => $combo->name, 'combo' => true],
            tenantId: $combo->tenant_id,
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StorePurchaseLineRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseLineRequest;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use App\Services\UnitConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
        private readonly UnitConverter $converter,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        $purchases = $tenant->purchases()
            ->with('supplier')
            ->withCount('lines')
            ->when(request('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(request('from'), fn ($q, $date) => $q->where('invoice_date', '>=', $date))
            ->when(request('to'), fn ($q, $date) => $q->where('invoice_date', '<=', $date))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();

        return view('purchases.index', compact('purchases', 'suppliers'));
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        abort_unless(
            $tenant->suppliers()->where('id', $request->validated('supplier_id'))->exists(),
            403,
        );

        $purchase = $tenant->purchases()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase',
            targetId: $purchase->id,
            action: 'purchase.created',
            payload: ['invoice_number' => $purchase->invoice_number, 'invoice_date' => $purchase->invoice_date->toDateString()],
            tenantId: $tenant->id,
        );

        return redirect()->route('purchases.show', $purchase)->with('status', 'Compra registrada.');
    }

    public function show(Purchase $purchase): View
    {
        $this->authorizePurchase($purchase);
        $tenant = app(Tenant::class);

        $purchase->load(['supplier', 'lines']);

        $ingredients = $tenant->ingredients()->where('active', true)->orderBy('name')->get();
        $packagings = $tenant->packagings()->where('active', true)->orderBy('name')->get();
        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();
        $units = Unit::cases();

        return view('purchases.show', compact('purchase', 'ingredients', 'packagings', 'suppliers', 'units'));
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($purchase);

        if ($purchase->lines()->exists()) {
            return back()->with('error', 'No se puede eliminar una compra que tiene ítems. Eliminá las líneas primero.');
        }

        $purchase->delete();

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase',
            targetId: $purchase->id,
            action: 'purchase.deleted',
            payload: ['invoice_number' => $purchase->invoice_number],
            tenantId: $purchase->tenant_id,
        );

        return redirect()->route('purchases.index')->with('status', 'Compra eliminada.');
    }

    public function storeLine(StorePurchaseLineRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($purchase);

        $data = $request->validated();
        $purchaseUnit = Unit::from($data['purchase_unit']);
        $subtotal = (float) $data['quantity_purchased'] * (float) $data['unit_price'];

        if ($data['purchaseable_type'] === 'ingredient') {
            $item = Ingredient::find($data['purchaseable_id']);
            abort_unless($item && $item->tenant_id === $purchase->tenant_id, 422, 'Ingrediente no válido.');

            $costPerUnit = $this->convertToCostPerUnit($purchaseUnit, $item->unit, (float) $data['unit_price']);
            abort_if($costPerUnit === null, 422, 'Las unidades no son compatibles con las del ingrediente.');

            $line = $purchase->lines()->create([
                ...$data,
                'subtotal' => $subtotal,
            ]);

            $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
            $item->update(['cost_per_unit' => $costPerUnit]);
            $this->propagator->propagateFromIngredient($item->id);

            $this->recorder->record(
                actor: $request->user(),
                targetType: 'purchase_line',
                targetId: $line->id,
                action: 'purchase_line.created',
                payload: ['ingredient' => $item->name, 'cost_per_unit' => $costPerUnit],
                tenantId: $purchase->tenant_id,
            );
        } else {
            $item = Packaging::find($data['purchaseable_id']);
            abort_unless($item && $item->tenant_id === $purchase->tenant_id, 422, 'Packaging no válido.');

            // Packaging is always per unit — unit must be 'u'
            abort_unless($purchaseUnit === Unit::Unidad, 422, 'El packaging solo puede comprarse por unidad (u).');

            $line = $purchase->lines()->create([
                ...$data,
                'subtotal' => $subtotal,
            ]);

            $item->priceLogs()->create(['cost_per_unit' => (float) $data['unit_price'], 'recorded_at' => now()]);
            $item->update(['cost_per_unit' => (float) $data['unit_price']]);
            $this->propagator->propagateFromPackaging($item->id);

            $this->recorder->record(
                actor: $request->user(),
                targetType: 'purchase_line',
                targetId: $line->id,
                action: 'purchase_line.created',
                payload: ['packaging' => $item->name, 'cost_per_unit' => (float) $data['unit_price']],
                tenantId: $purchase->tenant_id,
            );
        }

        return back()->with('status', 'Ítem agregado y costo actualizado.');
    }

    public function updateLine(UpdatePurchaseLineRequest $request, Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorizePurchase($purchase);
        abort_unless($line->purchase_id === $purchase->id, 403);

        $data = $request->validated();
        $purchaseUnit = Unit::from($data['purchase_unit']);
        $subtotal = (float) $data['quantity_purchased'] * (float) $data['unit_price'];

        if ($line->isIngredient()) {
            $item = Ingredient::find($line->purchaseable_id);
            abort_unless($item, 422);

            $costPerUnit = $this->convertToCostPerUnit($purchaseUnit, $item->unit, (float) $data['unit_price']);
            abort_if($costPerUnit === null, 422, 'Las unidades no son compatibles con las del ingrediente.');

            $line->update([...$data, 'subtotal' => $subtotal]);

            $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
            $item->update(['cost_per_unit' => $costPerUnit]);
            $this->propagator->propagateFromIngredient($item->id);
        } else {
            $item = Packaging::find($line->purchaseable_id);
            abort_unless($item, 422);
            abort_unless($purchaseUnit === Unit::Unidad, 422, 'El packaging solo puede comprarse por unidad (u).');

            $line->update([...$data, 'subtotal' => $subtotal]);

            $item->priceLogs()->create(['cost_per_unit' => (float) $data['unit_price'], 'recorded_at' => now()]);
            $item->update(['cost_per_unit' => (float) $data['unit_price']]);
            $this->propagator->propagateFromPackaging($item->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.updated',
            payload: ['type' => $line->purchaseable_type, 'id' => $line->purchaseable_id],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Ítem actualizado y costo recalculado.');
    }

    public function destroyLine(Purchase $purchase, PurchaseLine $line): RedirectResponse
    {
        $this->authorizePurchase($purchase);
        abort_unless($line->purchase_id === $purchase->id, 403);

        $line->delete();

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase_line',
            targetId: $line->id,
            action: 'purchase_line.deleted',
            payload: ['type' => $line->purchaseable_type, 'id' => $line->purchaseable_id],
            tenantId: $purchase->tenant_id,
        );

        return back()->with('status', 'Ítem eliminado.');
    }

    /**
     * Convert a purchase unit_price to the ingredient's base unit cost.
     * E.g.: buying 1kg at $500/kg → ingredient.unit = gr → $0.50/gr
     */
    private function convertToCostPerUnit(Unit $purchaseUnit, Unit $ingredientUnit, float $unitPrice): ?float
    {
        if ($purchaseUnit === $ingredientUnit) {
            return $unitPrice;
        }

        if (! $this->converter->compatible($purchaseUnit, $ingredientUnit)) {
            return null;
        }

        // Convert 1 purchase_unit to ingredient_units → price scales inversely
        $purchaseUnitInIngredientUnits = $this->converter->convert(1.0, $purchaseUnit, $ingredientUnit);

        if ($purchaseUnitInIngredientUnits === null || $purchaseUnitInIngredientUnits == 0.0) {
            return null;
        }

        return $unitPrice / $purchaseUnitInIngredientUnits;
    }

    private function authorizePurchase(Purchase $purchase): void
    {
        abort_unless($purchase->tenant_id === app(Tenant::class)->id, 403);
    }
}

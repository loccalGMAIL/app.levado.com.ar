<?php

namespace App\Services;

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;

/**
 * Records purchase lines and keeps the related item's cost in sync.
 *
 * Two phases:
 *  - storePending(): captures a line as read from the invoice, WITHOUT touching costs.
 *  - apply(): imputes the line's price to its matched item (price log + propagation).
 *
 * record() = storePending() + apply(), used by the manual line form where the cost
 * is updated immediately.
 */
class PurchaseLineRecorder
{
    public function __construct(
        private readonly RecipeCostPropagator $propagator,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * Create a line and impute its cost immediately (manual line form).
     *
     * @param  array{purchaseable_type: string, purchaseable_id: int|string, quantity_purchased: float|string, purchase_unit: string, unit_price: float|string}  $data
     */
    public function record(Purchase $purchase, array $data): PurchaseLine
    {
        $line = $this->storePending($purchase, $data);
        $this->apply($line);

        return $line;
    }

    /**
     * Capture a line as read from the invoice, without imputing any cost.
     * The match (purchaseable_*) is optional — it can be a suggestion or null.
     *
     * @param  array{raw_name?: ?string, purchaseable_type?: ?string, purchaseable_id?: int|string|null, quantity_purchased: float|string, purchase_unit: string, unit_price: float|string}  $data
     */
    public function storePending(Purchase $purchase, array $data): PurchaseLine
    {
        $subtotal = (float) $data['quantity_purchased'] * (float) $data['unit_price'];

        return $purchase->lines()->create([
            'raw_name' => $data['raw_name'] ?? null,
            'purchaseable_type' => $data['purchaseable_type'] ?? null,
            'purchaseable_id' => $data['purchaseable_id'] ?? null,
            'quantity_purchased' => $data['quantity_purchased'],
            'purchase_unit' => $data['purchase_unit'],
            'unit_price' => $data['unit_price'],
            'iva_rate' => $data['iva_rate'] ?? 0.21,
            'subtotal' => $subtotal,
        ]);
    }

    /**
     * Impute the line's price to its matched item and mark it as applied.
     * Aborts (422) if the line is unmatched or the units are incompatible.
     */
    public function apply(PurchaseLine $line): void
    {
        abort_unless($line->isMatched(), 422, 'La línea no tiene un ítem asociado.');

        $purchaseUnit = $line->purchase_unit;

        if ($line->isIngredient()) {
            $item = Ingredient::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Ingrediente no válido.');

            $costPerUnit = $this->costPerUnit($purchaseUnit, $item->unit, (float) $line->unit_price);
            abort_if($costPerUnit === null, 422, 'Las unidades no son compatibles con las del ingrediente.');

            $this->applyIngredientCost($item, $costPerUnit);
        } else {
            $item = Packaging::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Packaging no válido.');
            abort_unless($purchaseUnit === Unit::Unidad, 422, 'El packaging solo puede comprarse por unidad (u).');

            $this->applyPackagingCost($item, (float) $line->unit_price);
        }

        $line->update(['cost_applied_at' => now()]);
    }

    /**
     * Update an existing line (description + amounts) and re-impute its cost.
     *
     * @param  array{raw_name?: ?string, quantity_purchased: float|string, purchase_unit: string, unit_price: float|string}  $data
     */
    public function recompute(PurchaseLine $line, array $data): void
    {
        $subtotal = (float) $data['quantity_purchased'] * (float) $data['unit_price'];

        $wasApplied = $line->isApplied();

        $line->update([
            'raw_name' => $data['raw_name'] ?? $line->raw_name,
            'quantity_purchased' => $data['quantity_purchased'],
            'purchase_unit' => $data['purchase_unit'],
            'unit_price' => $data['unit_price'],
            'iva_rate' => $data['iva_rate'] ?? $line->iva_rate,
            'subtotal' => $subtotal,
        ]);

        // Re-impute only if the line had already been applied; a still-pending
        // line just updates its amounts and stays pending.
        if ($wasApplied) {
            $this->apply($line);
        }
    }

    /**
     * Apply an explicit unit cost without going through unit conversion.
     * Used when the user provides the cost per ingredient/packaging unit directly
     * (e.g., for purchases made in 'unidad' where the catalog stores per kg).
     */
    public function applyWithCost(PurchaseLine $line, float $unitCost): void
    {
        abort_unless($line->isMatched(), 422, 'La línea no tiene un ítem asociado.');

        if ($line->isIngredient()) {
            $item = Ingredient::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Ingrediente no válido.');
            $this->applyIngredientCost($item, $unitCost);
        } else {
            $item = Packaging::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Packaging no válido.');
            $this->applyPackagingCost($item, $unitCost);
        }

        $line->update(['cost_applied_at' => now()]);
    }

    private function applyIngredientCost(Ingredient $item, float $costPerUnit): void
    {
        $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
        $item->update(['cost_per_unit' => $costPerUnit]);
        $this->propagator->propagateFromIngredient($item->id);
    }

    private function applyPackagingCost(Packaging $item, float $costPerUnit): void
    {
        $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
        $item->update(['cost_per_unit' => $costPerUnit]);
        $this->propagator->propagateFromPackaging($item->id);
    }

    /**
     * Convert a purchase unit_price to the ingredient's base unit cost.
     * E.g.: buying 1kg at $500/kg → ingredient.unit = gr → $0.50/gr
     */
    public function costPerUnit(Unit $purchaseUnit, Unit $ingredientUnit, float $unitPrice): ?float
    {
        if ($purchaseUnit === $ingredientUnit) {
            return $unitPrice;
        }

        if (! $this->converter->compatible($purchaseUnit, $ingredientUnit)) {
            return null;
        }

        $purchaseUnitInIngredientUnits = $this->converter->convert(1.0, $purchaseUnit, $ingredientUnit);

        if ($purchaseUnitInIngredientUnits === null || $purchaseUnitInIngredientUnits == 0.0) {
            return null;
        }

        return $unitPrice / $purchaseUnitInIngredientUnits;
    }
}

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
     *
     * For ingredient lines where units don't share a dimension (e.g. u → kg),
     * falls back to parsing the product description for a quantity hint
     * (same logic as the JS parseDesc() + pkgQty in match.blade.php).
     */
    public function apply(PurchaseLine $line): void
    {
        abort_unless($line->isMatched(), 422, 'La línea no tiene un ítem asociado.');

        $purchaseUnit = $line->purchase_unit;

        if ($line->isIngredient()) {
            $item = Ingredient::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Ingrediente no válido.');

            $costPerUnit = $this->costPerUnit($purchaseUnit, $item->unit, (float) $line->unit_price);

            if ($costPerUnit === null) {
                $pkgQty = $this->parseDescPkgQty($line->raw_name ?? '', $item->unit);
                abort_if($pkgQty === null || $pkgQty <= 0, 422, 'Las unidades no son compatibles con las del ingrediente.');
                $costPerUnit = (float) $line->unit_price / $pkgQty;
            }

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

    /**
     * Parse a product description for a quantity hint when purchase unit and ingredient
     * unit are dimensionally incompatible (e.g. 'u' → 'kg').
     *
     * Mirrors the JS parseDesc() + pkgQty logic in match.blade.php.
     * Matches patterns like "X 25 Kg", "x5lts", "× 200 ml", "X 1.5 L", "X5KG".
     *
     * Returns the package quantity expressed in $ingredientUnit, or null if not parseable.
     */
    private function parseDescPkgQty(string $desc, Unit $ingredientUnit): ?float
    {
        $pattern = '/[xX×]\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs?|kilo[s]?|kilogramo[s]?|gr[s]?|gramo[s]?|g|l(?:t[s]?|itro[s]?)?|ml[s]?|cc)/i';

        if (! preg_match($pattern, $desc, $matches)) {
            return null;
        }

        $qty = (float) str_replace(',', '.', $matches[1]);
        if ($qty <= 0) {
            return null;
        }

        $aliases = [
            'kg' => Unit::Kilogramo, 'kgs' => Unit::Kilogramo,
            'kilo' => Unit::Kilogramo, 'kilos' => Unit::Kilogramo,
            'kilogramo' => Unit::Kilogramo, 'kilogramos' => Unit::Kilogramo,
            'gr' => Unit::Gramo, 'grs' => Unit::Gramo, 'g' => Unit::Gramo,
            'gramo' => Unit::Gramo, 'gramos' => Unit::Gramo,
            'l' => Unit::Litro, 'lt' => Unit::Litro, 'lts' => Unit::Litro,
            'litro' => Unit::Litro, 'litros' => Unit::Litro,
            'ml' => Unit::Mililitro, 'mls' => Unit::Mililitro,
            'cc' => Unit::Centimetro3,
        ];

        $parsedUnit = $aliases[strtolower($matches[2])] ?? null;
        if ($parsedUnit === null) {
            return null;
        }

        return $this->converter->convert($qty, $parsedUnit, $ingredientUnit);
    }
}

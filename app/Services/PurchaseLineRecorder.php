<?php

namespace App\Services;

use App\Enums\CostingMethod;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Support\Facades\DB;

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
        private readonly StockService $stock,
        private readonly NotificationService $notifications,
        private readonly ProductLinkMemory $linkMemory,
    ) {}

    /**
     * Create a line and impute its cost immediately (manual line form).
     *
     * @param  array{purchaseable_type: string, purchaseable_id: int|string, quantity_purchased: float|string, purchase_unit: string, unit_price: float|string}  $data
     */
    public function record(Purchase $purchase, array $data): PurchaseLine
    {
        return DB::transaction(function () use ($purchase, $data) {
            $line = $this->storePending($purchase, $data);
            $this->apply($line);

            return $line;
        });
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
            'percepcion_rate' => isset($data['percepcion_rate']) && $data['percepcion_rate'] !== '' ? $data['percepcion_rate'] : null,
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
            $stockQuantity = $this->converter->convert((float) $line->quantity_purchased, $purchaseUnit, $item->unit);

            if ($costPerUnit === null) {
                // El divisor recordado de una factura anterior gana sobre el que se
                // adivina de la descripción: alguien ya lo confirmó a mano. Es lo que
                // permite que "Aplicar N sugerencias" resuelva renglones de unidades
                // incompatibles, que antes salteaba siempre.
                $pkgQty = $this->rememberedPkgQty($line)
                    ?? $this->parseDescPkgQty($line->raw_name ?? '', $item->unit);
                abort_if($pkgQty === null || $pkgQty <= 0, 422, 'Las unidades no son compatibles con las del ingrediente.');
                $costPerUnit = (float) $line->unit_price / $pkgQty;
                $stockQuantity = (float) $line->quantity_purchased * $pkgQty;
            }

            // When ingredient tracks sub-units (subdivisions), the stored cost_per_unit must
            // represent the sub-unit price so that recipes can multiply directly without division.
            if ($item->subdivisions && $purchaseUnit === Unit::Unidad && $item->unit === Unit::Unidad) {
                $item->cost_per_package = $costPerUnit;
                $costPerUnit = $costPerUnit / $item->subdivisions;
                $stockQuantity = (float) $line->quantity_purchased * $item->subdivisions;
            } else {
                $item->cost_per_package = null;
            }

            $this->applyIngredientCost($item, $costPerUnit, $line);
        } elseif ($line->isProduct()) {
            $item = Product::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id && $item->isResale(), 422, 'Producto de reventa no válido.');

            // Un producto de reventa se compra como un ingrediente sin subdivisiones:
            // se convierte la cantidad a su unidad y el costo se guarda por unidad.
            $costPerUnit = $this->costPerUnit($purchaseUnit, $item->unit, (float) $line->unit_price);
            $stockQuantity = $this->converter->convert((float) $line->quantity_purchased, $purchaseUnit, $item->unit);

            if ($costPerUnit === null) {
                $pkgQty = $this->parseDescPkgQty($line->raw_name ?? '', $item->unit);
                abort_if($pkgQty === null || $pkgQty <= 0, 422, 'Las unidades no son compatibles con las del producto.');
                $costPerUnit = (float) $line->unit_price / $pkgQty;
                $stockQuantity = (float) $line->quantity_purchased * $pkgQty;
            }

            $this->applyProductCost($item, $costPerUnit, (float) $stockQuantity);
        } else {
            $item = Packaging::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Packaging no válido.');
            abort_unless($purchaseUnit === Unit::Unidad, 422, 'El packaging solo puede comprarse por unidad (u).');

            $packagePrice = (float) $line->unit_price;
            if ($item->subdivisions) {
                $item->cost_per_package = $packagePrice;
                $costPerUnit = $packagePrice / $item->subdivisions;
                $stockQuantity = (float) $line->quantity_purchased * $item->subdivisions;
            } else {
                $item->cost_per_package = null;
                $costPerUnit = $packagePrice;
                $stockQuantity = (float) $line->quantity_purchased;
            }

            $this->applyPackagingCost($item, $costPerUnit, $line);
        }

        if ($stockQuantity !== null && $stockQuantity > 0) {
            $this->stock->syncPurchaseLineEntry($line, $item, $stockQuantity, $costPerUnit, auth()->user());
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
            'percepcion_rate' => array_key_exists('percepcion_rate', $data)
                ? (isset($data['percepcion_rate']) && $data['percepcion_rate'] !== '' ? $data['percepcion_rate'] : null)
                : $line->percepcion_rate,
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
            $item->cost_per_package = $this->packagePriceFor($item, $unitCost);
            $this->applyIngredientCost($item, $unitCost, $line);
        } elseif ($line->isProduct()) {
            $item = Product::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id && $item->isResale(), 422, 'Producto de reventa no válido.');
            // Cantidad comprada en unidades del producto (mismo divisor que syncStockFromExplicitCost).
            $purchasedQty = $unitCost > 0 ? (float) $line->quantity_purchased * ((float) $line->unit_price / $unitCost) : 0.0;
            $this->applyProductCost($item, $unitCost, $purchasedQty);
        } else {
            $item = Packaging::find($line->purchaseable_id);
            abort_unless($item && $item->tenant_id === $line->purchase->tenant_id, 422, 'Packaging no válido.');
            $item->cost_per_package = $this->packagePriceFor($item, $unitCost);
            $this->applyPackagingCost($item, $unitCost, $line);
        }

        $this->syncStockFromExplicitCost($line, $item, $unitCost);

        $line->update(['cost_applied_at' => now()]);
    }

    /**
     * El costo explícito ya viene expresado por sub-unidad (lo divide match.js).
     * El precio del bulto se deriva de vuelta para que ambas columnas queden
     * coherentes aun si el usuario sobrescribió el costo a mano al vincular.
     */
    private function packagePriceFor(Ingredient|Packaging $item, float $unitCost): ?float
    {
        $tracksSubUnits = $item->subdivisions
            && ($item instanceof Packaging || $item->unit === Unit::Unidad);

        return $tracksSubUnits ? $unitCost * $item->subdivisions : null;
    }

    /**
     * Stock para líneas con costo unitario explícito (unidades incompatibles):
     * el divisor precio-del-bulto / costo-por-unidad da cuántas unidades de
     * catálogo trae cada bulto. Si el costo no permite derivarlo, se imputa el
     * costo igual y la línea queda sin movimiento de stock.
     */
    private function syncStockFromExplicitCost(PurchaseLine $line, Ingredient|Packaging|Product $item, float $unitCost): void
    {
        if ($unitCost <= 0) {
            return;
        }

        $pkgQty = (float) $line->unit_price / $unitCost;

        if (! is_finite($pkgQty) || $pkgQty <= 0) {
            return;
        }

        $stockQuantity = (float) $line->quantity_purchased * $pkgQty;

        $this->stock->syncPurchaseLineEntry($line, $item, $stockQuantity, $unitCost, auth()->user());
    }

    private function applyIngredientCost(Ingredient $item, float $costPerUnit, PurchaseLine $line): void
    {
        $oldCost = (float) $item->cost_per_unit;
        $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
        $item->update(['cost_per_unit' => $costPerUnit, 'cost_per_package' => $item->cost_per_package]);
        $this->propagator->propagateFromIngredient($item->id);
        $this->notifications->raiseCostSpike($line, $item, $oldCost, $costPerUnit);
    }

    private function applyPackagingCost(Packaging $item, float $costPerUnit, PurchaseLine $line): void
    {
        $oldCost = (float) $item->cost_per_unit;
        $item->priceLogs()->create(['cost_per_unit' => $costPerUnit, 'recorded_at' => now()]);
        $item->update(['cost_per_unit' => $costPerUnit, 'cost_per_package' => $item->cost_per_package]);
        $this->propagator->propagateFromPackaging($item->id);
        $this->notifications->raiseCostSpike($line, $item, $oldCost, $costPerUnit);
    }

    /**
     * Un producto de reventa no interviene en el costo de ninguna receta, así que
     * comprarlo solo actualiza su cost_per_unit (sin price log ni propagación ni alerta).
     * Según el método de costeo efectivo: último costo, o promedio ponderado entre el
     * stock existente (a su costo vigente) y lo comprado. El promedio se calcula ANTES
     * del alta de stock de esta compra (que ocurre después).
     */
    private function applyProductCost(Product $item, float $costPerUnit, float $purchasedQty): void
    {
        $item->loadMissing('tenant');
        $tenant = $item->tenant;
        $default = CostingMethod::tryFrom((string) $tenant->getSetting('resale.costing_method', CostingMethod::LastCost->value))
            ?? CostingMethod::LastCost;

        $newCost = $costPerUnit;

        if ($item->effectiveCostingMethod($default) === CostingMethod::WeightedAverage && $purchasedQty > 0) {
            $qty = (float) ($this->stock->levelFor($item, $tenant->defaultLocation())?->quantity ?? 0);
            if ($qty > 0) {
                $oldCost = (float) $item->cost_per_unit;
                $newCost = ($qty * $oldCost + $purchasedQty * $costPerUnit) / ($qty + $purchasedQty);
            }
        }

        $item->update(['cost_per_unit' => round($newCost, 4)]);
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
     * Divisor confirmado a mano en una factura anterior del mismo proveedor, si
     * sigue apuntando al ítem con el que está asociado este renglón.
     */
    private function rememberedPkgQty(PurchaseLine $line): ?float
    {
        $purchase = $line->purchase;

        if ($purchase === null) {
            return null;
        }

        $hit = $this->linkMemory->recall($purchase->tenant, $purchase->supplier_id, $line->raw_name);

        if ($hit === null || $hit['pkg_qty'] === null) {
            return null;
        }

        $sameItem = $hit['purchaseable_type'] === $line->purchaseable_type
            && $hit['purchaseable_id'] === (int) $line->purchaseable_id;

        return $sameItem ? $hit['pkg_qty'] : null;
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

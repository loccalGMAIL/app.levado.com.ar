<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\ProductionStatus;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Motor de Producción: fabricar un producto elaborado descuenta los insumos base
 * de su receta (BOM explotado, sub-recetas phantom) y suma stock del producto.
 *
 * La cantidad a producir se expresa en la unidad del producto; el factor de escala
 * se calcula contra el rendimiento de la receta. El stock negativo está permitido
 * (convención del ledger): un faltante no bloquea la producción, solo se informa
 * en el preview.
 */
class ProductionService
{
    public function __construct(
        private RecipeExploder $exploder,
        private StockService $stock,
        private UnitConverter $converter,
        private NotificationService $notifications,
    ) {}

    /**
     * Preview sin escritura: insumos a consumir, faltantes y costo total
     * (insumos + mano de obra).
     *
     * @return array{lines: array<int, array{type: CatalogItemType, id: int, name: string, quantity: float, available: float, shortfall: float, unit_cost: float, line_cost: float}>, material_cost: float, labor_cost: float, total_cost: float}
     */
    public function preview(Product $product, float $quantity): array
    {
        $location = $product->tenant->defaultLocation();
        $consumption = $this->baseConsumption($product, $quantity);

        return $this->summarize($consumption['items'], $location, $consumption['labor_cost']);
    }

    /**
     * Insumos base (sin resumir) y mano de obra que consumiría fabricar
     * $quantity unidades de $product, sin escribir nada. Expone lo que
     * preview()/produce() ya calculaban internamente para que
     * ProductionOrderService pueda combinar el consumo de varios artículos en
     * una sola orden.
     *
     * @return array{items: Collection<int, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>, labor_cost: float}
     */
    public function baseConsumption(Product $product, float $quantity): array
    {
        $recipe = $this->guardProducible($product, $quantity);
        $factor = $this->factorFor($product, $recipe, $quantity);
        $exploded = $this->exploder->explodeWithLabor($recipe, $factor);

        return ['items' => $exploded['items'], 'labor_cost' => $exploded['labor_cost']];
    }

    /**
     * Arma las líneas de preview (con disponibilidad y costo) a partir de un
     * consumo base ya calculado — compartido por preview() y por el preview
     * combinado de una orden de producción. La mano de obra no es un ítem
     * stockeable: no genera línea, se suma aparte al total.
     *
     * @param  Collection<int, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>  $items
     * @return array{lines: array<int, array{type: CatalogItemType, id: int, name: string, quantity: float, available: float, shortfall: float, unit_cost: float, line_cost: float}>, material_cost: float, labor_cost: float, total_cost: float}
     */
    public function summarize(Collection $items, Location $location, float $laborCost): array
    {
        $lines = $items->map(function (array $entry) use ($location) {
            $item = $entry['item'];
            $available = (float) ($this->stock->levelFor($item, $location)?->quantity ?? 0);
            $unitCost = (float) $item->cost_per_unit;

            return [
                'type' => $entry['type'],
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $entry['type'] === CatalogItemType::Ingredient ? $item->unit->short() : Unit::Unidad->short(),
                'quantity' => $entry['quantity'],
                'available' => $available,
                'shortfall' => max(0.0, $entry['quantity'] - $available),
                'unit_cost' => $unitCost,
                'line_cost' => $entry['quantity'] * $unitCost,
            ];
        })->values()->all();

        $materialCost = array_sum(array_column($lines, 'line_cost'));

        return [
            'lines' => $lines,
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'total_cost' => $materialCost + $laborCost,
        ];
    }

    /**
     * Fabrica $quantity unidades del producto: descuenta insumos y suma el elaborado,
     * todo en una transacción. Devuelve el cabezal de la producción.
     */
    public function produce(Product $product, float $quantity, ?string $notes, User $user): Production
    {
        $recipe = $this->guardProducible($product, $quantity);
        $factor = $this->factorFor($product, $recipe, $quantity);
        $location = $product->tenant->defaultLocation();

        $exploded = $this->exploder->explodeWithLabor($recipe, $factor);
        $base = $exploded['items'];
        $laborCost = $exploded['labor_cost'];
        $materialCost = $base->sum(fn (array $entry) => $entry['quantity'] * (float) $entry['item']->cost_per_unit);
        $totalCost = $materialCost + $laborCost;
        $productUnitCost = $quantity > 0 ? $totalCost / $quantity : 0.0;

        // Se lee antes de abrir la transacción (y solo confirmadas: una anulada
        // no sirve de referencia) para no alargar la ventana de los locks
        // pesimistas que toma registerMovement() sobre stock_levels.
        $previousCost = (float) ($product->productions()
            ->where('status', ProductionStatus::Confirmed->value)
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->value('unit_cost') ?? 0);

        $production = DB::transaction(function () use ($product, $recipe, $quantity, $notes, $user, $location, $base, $materialCost, $laborCost, $totalCost, $productUnitCost) {
            $production = $product->tenant->productions()->create([
                'location_id' => $location->id,
                'product_id' => $product->id,
                'recipe_id' => $recipe->id,
                'quantity' => $quantity,
                'unit' => $product->unit->value,
                'unit_cost' => $productUnitCost,
                'material_cost' => $materialCost,
                'labor_cost' => $laborCost,
                'total_cost' => $totalCost,
                'status' => ProductionStatus::Confirmed->value,
                'notes' => $notes,
                'user_id' => $user->id,
                'produced_at' => now(),
            ]);

            // Los movimientos se emiten en orden determinista por (stockable_type,
            // stockable_id) para que dos producciones concurrentes tomen los locks
            // de stock_levels en el mismo orden y no se produzcan deadlocks. La
            // entrada del elaborado participa del mismo orden.
            $movements = $base->map(fn (array $entry) => [
                'sort' => $this->sortKey($entry['type'], $entry['item']->id),
                'item' => $entry['item'],
                'quantity' => -$entry['quantity'], // salida de insumo
                'unit_cost' => (float) $entry['item']->cost_per_unit,
            ]);

            $movements->push([
                'sort' => $this->sortKey(CatalogItemType::Product, $product->id),
                'item' => $product,
                'quantity' => $quantity, // entrada del elaborado
                'unit_cost' => $productUnitCost,
            ]);

            foreach ($movements->sortBy('sort')->values() as $movement) {
                $this->stock->registerMovement(
                    item: $movement['item'],
                    location: $location,
                    type: StockMovementType::Production,
                    quantity: $movement['quantity'],
                    unitCost: $movement['unit_cost'],
                    user: $user,
                    referenceType: 'production',
                    referenceId: $production->id,
                );
            }

            return $production;
        });

        // Fuera de la transacción: no es parte de lo que hay que revertir si
        // algo falla, y no debe extender los locks de stock_levels.
        $this->notifications->raiseProductionCostSpike($production, $product, $previousCost, $productUnitCost);

        return $production;
    }

    /**
     * Anula una producción: revierte todos sus movimientos con contramovimientos
     * exactos y marca el cabezal como anulado. Idempotente si ya está anulada.
     */
    public function cancel(Production $production, User $user): void
    {
        if ($production->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($production, $user) {
            $this->stock->reverseMovementsFor('production', $production->id, $user);

            $production->update([
                'status' => ProductionStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
        });

        // No dejar en el feed una alerta que apunta a una producción ya anulada.
        // $production->tenant() es directo (belongsTo propio): evita el lazy
        // load de ->product->tenant, que preventLazyLoading() no permite.
        $this->notifications->resolveByDedupeKey($production->tenant, "cost_spike:production:{$production->id}");
    }

    private function guardProducible(Product $product, float $quantity): Recipe
    {
        abort_unless($product->isManufactured(), 422, 'Solo se pueden producir productos elaborados.');
        abort_unless($quantity > 0, 422, 'La cantidad a producir debe ser mayor a cero.');

        $recipe = $product->recipe;
        abort_unless($recipe !== null && $recipe->active, 422, 'El producto no tiene una receta activa asociada.');

        return $recipe;
    }

    private function factorFor(Product $product, Recipe $recipe, float $quantity): float
    {
        $inYieldUnit = $this->converter->convert($quantity, $product->unit, $recipe->yield_unit);
        abort_unless($inYieldUnit !== null, 422, 'La unidad del producto no es compatible con el rendimiento de la receta.');
        abort_unless((float) $recipe->yield_quantity > 0, 422, 'La receta no tiene un rendimiento válido.');

        return $inYieldUnit / (float) $recipe->yield_quantity;
    }

    private function sortKey(CatalogItemType $type, int $id): string
    {
        return $type->value.':'.str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }
}

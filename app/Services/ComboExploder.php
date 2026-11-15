<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\StockMovementType;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Traduce un combo a sus componentes. El combo no tiene stock propio: cuando
 * sale, lo que baja es el stock de cada artículo que lo compone.
 *
 * registerSale() es el punto de entrada que va a usar Ventas; hoy nada lo
 * llama desde la UI.
 */
class ComboExploder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Componentes y cantidades que consumen $quantity unidades del combo,
     * ordenados por id de artículo.
     *
     * @return Collection<int, array{item: Product, quantity: float}>
     */
    public function explode(Product $combo, float $quantity): Collection
    {
        abort_unless($combo->isCombo(), 422, 'El artículo no es un combo.');

        $combo->loadMissing('comboLines.component.recipe');

        $components = collect();

        foreach ($combo->comboLines->sortBy('product_id') as $line) {
            if ($line->component !== null) {
                $components->push(['item' => $line->component, 'quantity' => (float) $line->quantity * $quantity]);
            }
        }

        return $components;
    }

    /**
     * Salida por venta de $quantity combos: un movimiento de venta por
     * componente, todos o ninguno. El stock negativo está permitido, igual que
     * en el resto del sistema.
     *
     * Sin referencia explícita los movimientos quedan apuntando al combo; Ventas
     * va a pasar su propio renglón para poder revertirlos con
     * StockService::reverseMovementsFor().
     *
     * @return Collection<int, StockMovement>
     */
    public function registerSale(
        Product $combo,
        float $quantity,
        Location $location,
        ?User $user = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): Collection {
        abort_unless($quantity > 0, 422, 'La cantidad de combos debe ser mayor a cero.');

        $components = $this->explode($combo, $quantity);
        abort_if($components->isEmpty(), 422, 'El combo no tiene componentes.');

        // Los componentes vienen ordenados por id: dos ventas concurrentes toman
        // los locks de stock_levels en el mismo orden y no se cruzan.
        return DB::transaction(fn () => $components->map(fn (array $component) => $this->stock->registerMovement(
            item: $component['item'],
            location: $location,
            type: StockMovementType::Sale,
            quantity: -$component['quantity'],
            unitCost: $component['item']->currentCost() ?? 0.0,
            reason: 'Combo: '.$combo->name,
            user: $user,
            referenceType: $referenceType ?? 'combo',
            referenceId: $referenceId ?? $combo->id,
        )));
    }

    /**
     * Cuántos combos enteros se pueden armar con el stock de la sucursal: lo
     * que alcance el componente más escaso. Un combo sin componentes da 0.
     *
     * @param  Collection<int, Product>  $combos  con comboLines cargadas
     * @return array<int, int> id de combo => combos disponibles
     */
    public function availability(Collection $combos, Location $location): array
    {
        $componentIds = $combos->flatMap(fn (Product $combo) => $combo->comboLines->pluck('product_id'))->unique();

        $stock = StockLevel::query()
            ->where('location_id', $location->id)
            ->where('stockable_type', CatalogItemType::Product->value)
            ->whereIn('stockable_id', $componentIds)
            ->pluck('quantity', 'stockable_id');

        return $combos->mapWithKeys(function (Product $combo) use ($stock) {
            $available = $combo->comboLines
                ->map(fn ($line) => (int) floor(max(0.0, (float) ($stock[$line->product_id] ?? 0)) / (float) $line->quantity))
                ->min();

            return [$combo->id => $available ?? 0];
        })->all();
    }
}

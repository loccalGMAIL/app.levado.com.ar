<?php

namespace App\Services;

use App\Models\CustomerOrderLine;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lo que sale un día: lo cargado a mano en las planillas de pedidos activas
 * más lo que piden los clientes activos, sumado por ARTÍCULO BASE y en sus
 * unidades. Es la entrada de la planilla de amasado.
 *
 * - Las presentaciones (pack x6) suman al artículo base: cada una vale
 *   `recipeUnitsPerItem()` unidades del base.
 * - Lo tipeado en bandejas o latas se pasa a unidades con lo que entra hoy en
 *   una; si la receta perdió el rinde se toma como unidades (igual que al
 *   guardar una planilla).
 * - Nunca crea semanas: si la semana del día todavía no existe se lee la que
 *   tendría al copiarse (`previewWeek()`), sin persistirla.
 * - Solo cuentan los artículos `producible()`.
 */
class DailyDemand
{
    public function __construct(
        private readonly OrderSheetWeekService $sheetWeeks,
        private readonly CustomerOrderTotals $customerTotals,
    ) {}

    /**
     * @return Collection<int, array{product: Product, manual: float, clients: float, units: float}> por id de artículo base, ordenado por nombre
     */
    public function forDate(int $tenantId, CarbonImmutable $date): Collection
    {
        $bases = $this->producibleBases($tenantId);
        $basesByRecipe = $bases->keyBy('recipe_id');

        /** @var array<int, array{product: Product, manual: float, clients: float, units: float}> $totals */
        $totals = [];

        foreach ($this->ordered($tenantId, $date) as [$product, $units, $source]) {
            [$base, $factor] = $this->baseOf($product, $bases, $basesByRecipe);

            if ($base === null || $units <= 0) {
                continue;
            }

            $entry = $totals[$base->id] ?? ['product' => $base, 'manual' => 0.0, 'clients' => 0.0, 'units' => 0.0];
            $entry[$source] += $units * $factor;
            $entry['units'] += $units * $factor;
            $totals[$base->id] = $entry;
        }

        uasort($totals, fn (array $a, array $b) => strcmp(mb_strtolower($a['product']->name), mb_strtolower($b['product']->name)));

        return collect($totals);
    }

    /**
     * Cada cosa pedida el día, tal como se pidió: el artículo, las unidades
     * del artículo y de dónde sale (lo manual de una planilla o un cliente).
     *
     * @return list<array{0: Product, 1: float, 2: 'manual'|'clients'}>
     */
    private function ordered(int $tenantId, CarbonImmutable $date): array
    {
        $day = $date->dayOfWeekIso - 1;
        $ordered = [];

        $sheets = OrderSheet::query()->where('tenant_id', $tenantId)->active()->orderBy('id')->get();

        foreach ($sheets as $sheet) {
            $week = $this->sheetWeeks->previewWeek($sheet, $date);

            foreach ($week === null ? [] : $week->lines as $line) {
                $ordered[] = [$line->product, $this->unitsOfDay($line, $day), 'manual'];
            }
        }

        foreach ($this->customerTotals->dayUnitsByProduct($tenantId, $date) as $entry) {
            $ordered[] = [$entry['product'], $entry['units'], 'clients'];
        }

        return $ordered;
    }

    /**
     * Artículos base que se pueden planificar, con su receta (la planilla lee
     * el recipiente y si pasa por cámara).
     *
     * @return Collection<int, Product>
     */
    private function producibleBases(int $tenantId): Collection
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->producible()
            ->with('recipe')
            ->get()
            ->keyBy('id');
    }

    /**
     * El artículo base al que suma un artículo pedido y cuántas unidades del
     * base vale cada unidad pedida. [null, 0] si no suma (no es producible ni
     * presentación de uno que lo sea).
     *
     * @param  Collection<int, Product>  $bases
     * @param  Collection<array-key, Product>  $basesByRecipe
     * @return array{0: Product|null, 1: float}
     */
    private function baseOf(Product $product, Collection $bases, Collection $basesByRecipe): array
    {
        if ($bases->has($product->id)) {
            return [$bases->get($product->id), 1.0];
        }

        if ($product->isRecipePresentation() && $basesByRecipe->has($product->recipe_id)) {
            return [$basesByRecipe->get($product->recipe_id), $product->recipeUnitsPerItem()];
        }

        return [null, 0.0];
    }

    /** Lo de un día de la fila, en unidades del artículo de la fila. */
    private function unitsOfDay(OrderSheetLine|CustomerOrderLine $line, int $day): float
    {
        $quantity = $line->quantities()[$day];
        $per = $line->container_type !== null ? $line->product->unitsPerContainer() : null;

        return $per !== null ? $quantity * $per : $quantity;
    }
}

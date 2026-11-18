<?php

namespace App\Services\Concerns;

use App\Enums\ContainerType;
use App\Models\CustomerOrderLine;
use App\Models\OrderSheetLine;
use App\Models\Product;
use App\Rules\TrayQuantity;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Lo común de toda grilla semanal de pedidos (la de una planilla y la de un
 * cliente): cuál es el lunes de una semana, cómo se persiste una fila (cada
 * una en la medida actual de su artículo) y cómo se presenta a la grilla.
 */
trait BuildsWeeklyGrid
{
    /** El lunes de la semana que contiene la fecha. */
    public function mondayOf(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    /** El lunes de la semana pedida (o de la actual si no viene una fecha válida). */
    public function mondayFromInput(string $date): CarbonImmutable
    {
        try {
            $base = $date !== '' ? CarbonImmutable::parse($date) : CarbonImmutable::now();
        } catch (Throwable) {
            $base = CarbonImmutable::now();
        }

        return $this->mondayOf($base);
    }

    /**
     * Una fila lista para la grilla, en la medida ACTUAL del artículo (la de su
     * receta). Si se guardó en otra medida (la receta ganó rinde después), se
     * convierte con el rinde actual. Si la receta perdió el rinde no hay con qué convertir:
     * se muestran las cantidades guardadas con un aviso.
     *
     * @return array{product_id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null, q: array<int, float>, warning: string|null}
     */
    public function presentLine(OrderSheetLine|CustomerOrderLine $line): array
    {
        $product = $line->product;
        $container = $product->containerPayload();
        $quantities = $line->quantities();
        $warning = null;

        if ($container !== null && $line->container_type === null) {
            $quantities = array_map(fn (float $units): float => TrayQuantity::roundToStep($units / $container['per']), $quantities);
        } elseif ($container === null && $line->container_type !== null) {
            $warning = "Se cargó en {$line->container_type->plural()}, pero la receta ya no tiene rinde: al guardar se toma como unidades.";
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->short(),
            'container' => $container,
            'q' => $quantities,
            'warning' => $warning,
        ];
    }

    /**
     * La medida actual (bandeja/lata o null) de cada artículo pedido, por id.
     *
     * @param  array<int, array{product_id: int|string, quantities: array<int, float|int|string|null>}>  $lines
     * @return array<int, ContainerType|null>
     */
    protected function containersFor(array $lines): array
    {
        return Product::query()
            ->whereIn('id', array_map(fn (array $line): int => (int) $line['product_id'], $lines))
            ->with('recipe')
            ->get(['id', 'recipe_id', 'recipe_quantity'])
            ->mapWithKeys(fn (Product $product) => [$product->id => $product->containerType()])
            ->all();
    }

    /**
     * @param  array<int, float|int|string|null>  $quantities
     * @return array<string, int|float|ContainerType|null>
     */
    protected function lineAttributes(int $position, int $productId, array $quantities, ?ContainerType $container): array
    {
        $attributes = ['product_id' => $productId, 'position' => $position, 'container_type' => $container];

        foreach (range(1, OrderSheetLine::DAYS) as $day) {
            $attributes["day_{$day}"] = max(0.0, (float) ($quantities[$day - 1] ?? 0));
        }

        return $attributes;
    }
}

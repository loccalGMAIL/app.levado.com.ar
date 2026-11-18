<?php

namespace App\Services;

use App\Models\CustomerOrderLine;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que piden los clientes, sumado. Alimenta dos cosas:
 *  - las planillas de pedidos: cada artículo tiene una planilla de destino
 *    (`products.order_sheet_id`) y lo que piden los clientes de la semana se
 *    muestra ahí junto a lo cargado a mano;
 *  - la hoja diaria del repartidor: sus clientes y lo que lleva a cada uno.
 *
 * Todo se suma en UNIDADES (una línea tipeada en bandejas vale bandejas × lo
 * que entra en una hoy) y recién al presentar se vuelve a la medida actual del
 * artículo. Solo cuentan los clientes activos. Devuelve arrays/escalares, no
 * modelos: la hoja se renderiza también para dompdf.
 */
class CustomerOrderTotals
{
    public function __construct(private readonly CustomerOrderWeekService $weeks) {}

    /**
     * Lo pedido por clientes en la semana para los artículos que suman en la
     * planilla, por artículo, en unidades (lunes a domingo).
     *
     * @return Collection<array-key, array{product: Product, units: list<float>}>
     */
    public function forSheet(OrderSheet $sheet, CarbonImmutable $monday): Collection
    {
        return $this->linesOfWeek($sheet->tenant_id, $monday)
            ->filter(fn (CustomerOrderLine $line) => $line->product->order_sheet_id === $sheet->id)
            ->groupBy('product_id')
            ->map(function (Collection $lines): array {
                /** @var CustomerOrderLine $first */
                $first = $lines->firstOrFail();
                $product = $first->product;
                assert($product instanceof Product);

                return ['product' => $product, 'units' => $this->sumUnits($lines)];
            });
    }

    /**
     * Agrega a las filas (ya presentadas) de una planilla lo que piden los
     * clientes: `clients` (lunes a domingo, en la medida de la fila). Los
     * artículos pedidos que todavía no están en la lista de la planilla se
     * suman como filas nuevas, con lo manual en cero.
     *
     * @param  list<array{product_id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null, q: array<int, float>, warning: string|null}>  $lines
     * @return list<array{product_id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null, q: array<int, float>, warning: string|null, clients: list<float>}>
     */
    public function overlay(OrderSheet $sheet, CarbonImmutable $monday, array $lines): array
    {
        $ordered = $this->forSheet($sheet, $monday);
        $present = [];

        $rows = array_map(function (array $line) use ($ordered, &$present): array {
            $present[$line['product_id']] = true;
            $units = $ordered->get($line['product_id'])['units'] ?? array_fill(0, OrderSheetLine::DAYS, 0.0);

            return $line + ['clients' => $this->inMeasure($units, $line['container']['per'] ?? null)];
        }, $lines);

        foreach ($ordered as $productId => $entry) {
            if (isset($present[$productId])) {
                continue;
            }

            $product = $entry['product'];
            $container = $product->containerPayload();

            $rows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit->short(),
                'container' => $container,
                'q' => array_fill(0, OrderSheetLine::DAYS, 0.0),
                'warning' => null,
                'clients' => $this->inMeasure($entry['units'], $container['per'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Hoja del repartidor para un día: sus clientes con pedido ese día (por
     * orden de recorrido) y lo que lleva cada uno, más lo que hay que cargar.
     * `$deliveryPersonId` null = clientes sin repartidor. Las cantidades van en
     * la medida actual de cada artículo.
     *
     * @return array{
     *     products: list<array{id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null}>,
     *     customers: list<array{name: string, address: string, cells: array<int, float>}>,
     *     totals: array<int, float>,
     *     totals_units: array<int, float>,
     * }
     */
    public function dayRoute(int $tenantId, CarbonImmutable $date, ?int $deliveryPersonId): array
    {
        $monday = $this->weeks->mondayOf($date);
        $day = $date->dayOfWeekIso - 1;

        $entries = $this->linesOfWeek($tenantId, $monday)
            ->filter(fn (CustomerOrderLine $line) => $line->week->customer->delivery_person_id === $deliveryPersonId)
            ->map(fn (CustomerOrderLine $line) => ['line' => $line, 'units' => $this->unitsOf($line)[$day]])
            ->filter(fn (array $entry) => $entry['units'] > 0);

        $products = $entries->map(fn (array $entry) => $entry['line']->product)->unique('id')->sortBy('name')->values();

        $customers = $entries
            ->groupBy(fn (array $entry) => $entry['line']->week->customer_id)
            ->map(fn (Collection $group) => [
                'customer' => $group->first()['line']->week->customer,
                'cells' => $group->mapWithKeys(fn (array $entry) => [
                    $entry['line']->product_id => $this->inMeasure([$entry['units']], $entry['line']->product->containerPayload()['per'] ?? null)[0],
                ])->all(),
            ])
            ->sortBy(fn (array $entry) => sprintf('%05d|%s', $entry['customer']->route_position ?? 99999, mb_strtolower($entry['customer']->name)))
            ->values();

        $totalsUnits = $entries
            ->groupBy(fn (array $entry) => $entry['line']->product_id)
            ->map(fn (Collection $group) => (float) $group->sum('units'))
            ->all();

        return [
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit->short(),
                'container' => $product->containerPayload(),
            ])->all(),
            'customers' => $customers->map(fn (array $entry) => [
                'name' => $entry['customer']->name,
                'address' => trim(implode(', ', array_filter([$entry['customer']->address, $entry['customer']->city]))),
                'cells' => $entry['cells'],
            ])->all(),
            'totals' => $products->mapWithKeys(fn (Product $product) => [
                $product->id => $this->inMeasure([$totalsUnits[$product->id]], $product->containerPayload()['per'] ?? null)[0],
            ])->all(),
            'totals_units' => $totalsUnits,
        ];
    }

    /**
     * Líneas de clientes activos del negocio en la semana, con lo necesario
     * para convertirlas (receta del artículo) y ubicarlas (cliente).
     *
     * @return Collection<int, CustomerOrderLine>
     */
    private function linesOfWeek(int $tenantId, CarbonImmutable $monday): Collection
    {
        return CustomerOrderLine::query()
            ->whereHas('week', fn (Builder $week) => $week
                ->where('tenant_id', $tenantId)
                ->whereDate('starts_on', $monday->toDateString())
                ->whereHas('customer', fn (Builder $customer) => $customer->where('active', true)))
            ->with(['product.recipe', 'week.customer'])
            ->get();
    }

    /**
     * Cantidades de la línea en unidades. Una línea tipeada en bandejas se
     * convierte con lo que entra hoy en una; si el artículo ya no tiene rinde
     * se toma como unidades (igual que al guardar).
     *
     * @return list<float>
     */
    private function unitsOf(CustomerOrderLine $line): array
    {
        $per = $line->container_type !== null ? $line->product->unitsPerContainer() : null;

        return array_map(fn (float $quantity): float => $per !== null ? $quantity * $per : $quantity, $line->quantities());
    }

    /**
     * @param  Collection<int, CustomerOrderLine>  $lines
     * @return list<float>
     */
    private function sumUnits(Collection $lines): array
    {
        $totals = array_fill(0, OrderSheetLine::DAYS, 0.0);

        foreach ($lines as $line) {
            foreach ($this->unitsOf($line) as $day => $units) {
                $totals[$day] += $units;
            }
        }

        return $totals;
    }

    /**
     * De unidades a la medida de la fila: recipientes si entra `$per`, unidades
     * si no. Sin redondear a cuartos (es lo pedido, no lo tipeado), solo a 3
     * decimales.
     *
     * @param  array<int, float>  $units
     * @return list<float>
     */
    private function inMeasure(array $units, ?float $per): array
    {
        return array_values(array_map(fn (float $value): float => round($per !== null ? $value / $per : $value, 3), $units));
    }
}

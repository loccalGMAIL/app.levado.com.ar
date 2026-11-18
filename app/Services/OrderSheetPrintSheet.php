<?php

namespace App\Services;

use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Arma los datos de la planilla de pedidos imprimible: una planilla, una
 * semana. Devuelve sólo arrays/escalares -la vista se renderiza también para
 * dompdf, y un lazy load ahí adentro deja un PDF corrupto en vez de un error
 * legible-. Nunca crea semanas: imprimir no modifica datos, así que una semana
 * sin cargar sale vacía (con lo que hayan pedido los clientes, si algo).
 *
 * Cada celda es el TOTAL del día: lo cargado a mano más lo que piden los
 * clientes de reparto, que es lo que hay que elaborar.
 *
 * El formato de cantidades replica el de la grilla en pantalla
 * (resources/js/orders/sheet-grid.js).
 */
class OrderSheetPrintSheet
{
    private const DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

    public function __construct(
        private readonly ReportLetterhead $letterhead,
        private readonly OrderSheetWeekService $weeks,
        private readonly CustomerOrderTotals $customerTotals,
    ) {}

    /**
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     title: string,
     *     period: string,
     *     generated_at: string,
     *     has_week: bool,
     *     days: list<array{name: string, date: string}>,
     *     rows: list<array{name: string, detail: string, warning: ?string, cells: list<array{qty: string, equivalent: string}>, total: string}>,
     * }
     */
    public function for(OrderSheet $sheet, CarbonImmutable $start): array
    {
        $week = $this->weeks->existingWeek($sheet, $start);

        $lines = $week === null
            ? []
            : $week->lines
                ->map(fn (OrderSheetLine $line) => $this->weeks->presentLine($line))
                ->values()
                ->all();

        $rows = array_map(
            fn (array $line) => $this->row($line),
            $this->customerTotals->overlay($sheet, $start, $lines),
        );

        return [
            'business' => $this->letterhead->for(app(Tenant::class)),
            'title' => $sheet->name,
            'period' => 'Semana del '.$start->format('d/m/Y').' al '.$start->addDays(6)->format('d/m/Y'),
            'generated_at' => CarbonImmutable::now()->format('d/m/Y H:i'),
            'has_week' => $week !== null,
            'days' => array_map(
                fn (int $offset) => ['name' => self::DAY_NAMES[$offset], 'date' => $start->addDays($offset)->format('d/m')],
                range(0, 6),
            ),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{product_id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null, q: array<int, float>, warning: string|null, clients: list<float>}  $line
     * @return array{name: string, detail: string, warning: ?string, cells: list<array{qty: string, equivalent: string}>, total: string}
     */
    private function row(array $line): array
    {
        $container = $line['container'];
        $quantities = array_map(fn (float $manual, float $clients): float => $manual + $clients, $line['q'], $line['clients']);
        $total = array_sum($quantities);

        return [
            'name' => $line['name'],
            'detail' => $container !== null
                ? $container['label'].' de '.$this->number($container['per']).' '.$line['unit']
                : $line['unit'],
            'warning' => $line['warning'],
            'cells' => array_map(fn (float $value) => [
                'qty' => $value > 0 ? $this->number($value) : '',
                'equivalent' => $value > 0 && $container !== null
                    ? $this->number($value * $container['per']).' '.$line['unit']
                    : '',
            ], $quantities),
            'total' => $container !== null
                ? $this->number($total).' '.$container['short'].' · '.$this->number($total * $container['per']).' '.$line['unit']
                : $this->number($total),
        ];
    }

    /** es-AR con hasta 3 decimales, sin ceros de más. */
    private function number(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}

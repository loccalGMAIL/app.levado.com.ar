<?php

namespace App\Services;

use App\Models\DeliveryPerson;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

/**
 * Arma los datos de la hoja diaria del repartidor: un día y un repartidor (o
 * los clientes sin repartidor). Filas = clientes en orden de recorrido,
 * columnas = artículos, y al pie lo que hay que cargar. Devuelve sólo
 * arrays/escalares: la vista se renderiza también para dompdf, y un lazy load
 * ahí adentro deja un PDF corrupto en vez de un error legible. Nunca crea nada.
 */
class DeliveryRouteSheet
{
    private const DAY_NAMES = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    public function __construct(
        private readonly ReportLetterhead $letterhead,
        private readonly CustomerOrderTotals $totals,
    ) {}

    /**
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     title: string,
     *     period: string,
     *     generated_at: string,
     *     products: list<array{id: int, name: string, detail: string}>,
     *     rows: list<array{number: int, name: string, address: string, cells: array<int, string>}>,
     *     load: array<int, string>,
     * }
     */
    public function for(Tenant $tenant, CarbonImmutable $date, ?DeliveryPerson $deliveryPerson): array
    {
        $route = $this->totals->dayRoute($tenant->id, $date, $deliveryPerson?->id);

        $containers = collect($route['products'])->mapWithKeys(fn (array $product) => [$product['id'] => $product['container']]);
        $units = collect($route['products'])->mapWithKeys(fn (array $product) => [$product['id'] => $product['unit']]);

        return [
            'business' => $this->letterhead->for($tenant),
            'title' => 'Reparto — '.($deliveryPerson === null ? 'Sin repartidor' : $deliveryPerson->name),
            'period' => ucfirst(self::DAY_NAMES[$date->dayOfWeekIso - 1]).' '.$date->format('d/m/Y'),
            'generated_at' => CarbonImmutable::now()->format('d/m/Y H:i'),
            'products' => array_map(fn (array $product) => [
                'id' => $product['id'],
                'name' => $product['name'],
                'detail' => $product['container'] !== null
                    ? $product['container']['label'].' de '.$this->number($product['container']['per']).' '.$product['unit']
                    : $product['unit'],
            ], $route['products']),
            'rows' => array_map(fn (array $customer, int $index) => [
                'number' => $index + 1,
                'name' => $customer['name'],
                'address' => $customer['address'],
                'cells' => collect($customer['cells'])
                    ->map(fn (float $quantity, int $productId) => $this->cell($quantity, $containers[$productId], $units[$productId]))
                    ->all(),
            ], $route['customers'], array_keys($route['customers'])),
            'load' => collect($route['totals'])
                ->map(fn (float $quantity, int $productId) => $this->cell($quantity, $containers[$productId], $units[$productId]))
                ->all(),
        ];
    }

    /**
     * La cantidad en la medida del artículo y, si es en bandejas o latas, las
     * unidades equivalentes entre paréntesis.
     *
     * @param  array{type: string, label: string, short: string, per: float}|null  $container
     */
    private function cell(float $quantity, ?array $container, string $unit): string
    {
        if ($container === null) {
            return $this->number($quantity);
        }

        return $this->number($quantity).' '.$container['short'].' ('.$this->number($quantity * $container['per']).' '.$unit.')';
    }

    /** es-AR con hasta 3 decimales, sin ceros de más. */
    private function number(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}

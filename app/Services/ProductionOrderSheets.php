<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma los datos de las planillas imprimibles de una orden de producción
 * (qué producir + insumos, y qué lleva cada repartidor). Como
 * FixedCostReport, devuelve sólo arrays/escalares -las vistas se renderizan
 * también para dompdf, y un lazy load ahí adentro deja un PDF corrupto en
 * vez de un error legible-.
 */
class ProductionOrderSheets
{
    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly ReportLetterhead $letterhead,
    ) {}

    /**
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     meta: array{number: string, type: string, date: ?string, generated_at: string},
     *     products: list<array{name: string, quantity: float, unit: string}>,
     *     ingredients: list<array{name: string, quantity: float, unit: string, available: float, shortfall: float}>,
     * }
     */
    public function production(ProductionOrder $order): array
    {
        $order->loadMissing(['location', 'tenant']);

        $aggregated = $this->orders->aggregate($order);
        $preview = $this->orders->previewFor($aggregated, $order->location);

        $products = $aggregated
            ->map(fn (array $entry) => [
                'name' => $entry['product']->name,
                'quantity' => $entry['quantity'],
                'unit' => $entry['product']->unit->short(),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        $ingredients = collect($preview['lines'])
            ->map(fn (array $line) => [
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'available' => $line['available'],
                'shortfall' => $line['shortfall'],
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'business' => $this->letterhead->for($order->tenant),
            'meta' => $this->meta($order),
            'products' => $products,
            'ingredients' => $ingredients,
        ];
    }

    /**
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     meta: array{number: string, type: string, date: ?string, generated_at: string},
     *     groups: list<array{key: string, title: string, subtitle: ?string, requests: list<array{number: string, destination: string, address: ?string, city: ?string, notes: ?string, lines: list<array{product: string, quantity: float, unit: string}>}>, totals: list<array{product: string, quantity: float, unit: string}>}>,
     * }
     */
    public function delivery(ProductionOrder $order): array
    {
        $order->loadMissing(['location', 'tenant']);

        $requests = $order->productionOrderRequests()
            ->with(['lines.product', 'destination' => fn ($morphTo) => $morphTo->morphWith([
                Customer::class => ['deliveryPerson'],
            ])])
            ->orderBy('position')
            ->get();

        $groups = $this->groupRequests($requests, $order->location);

        return [
            'business' => $this->letterhead->for($order->tenant),
            'meta' => $this->meta($order),
            'groups' => $groups->all(),
        ];
    }

    /**
     * @param  Collection<int, ProductionOrderRequest>  $requests
     * @return Collection<int, array{key: string, kind: string, title: string, subtitle: ?string, requests: list<array<string, mixed>>, totals: list<array<string, mixed>>}>
     */
    private function groupRequests(Collection $requests, Location $orderLocation): Collection
    {
        $buckets = collect();

        foreach ($requests as $request) {
            $destination = $request->destination;

            // Grupo de sucursal: sin subtítulo de dirección -queda en la línea
            // del pedido, en el mismo formato "número — destino — dirección"
            // que usan los pedidos de reparto-.
            [$key, $kind, $title, $subtitle] = match (true) {
                $destination instanceof Customer && $destination->deliveryPerson !== null => [
                    'driver-'.$destination->deliveryPerson->id,
                    'driver',
                    $destination->deliveryPerson->name,
                    $destination->deliveryPerson->phone,
                ],
                $destination instanceof Customer => ['location-'.$orderLocation->id, 'location', $orderLocation->name, null],
                $destination instanceof Location => ['location-'.$destination->id, 'location', $destination->name, null],
                default => ['location-'.$orderLocation->id, 'location', $orderLocation->name, null],
            };

            $bucket = $buckets->get($key) ?? ['key' => $key, 'kind' => $kind, 'title' => $title, 'subtitle' => $subtitle, 'requests' => collect()];
            $bucket['requests']->push($this->requestPayload($request, $destination, $orderLocation));
            $buckets->put($key, $bucket);
        }

        return $buckets
            ->map(fn (array $bucket) => [
                'key' => $bucket['key'],
                'kind' => $bucket['kind'],
                'title' => $bucket['title'],
                'subtitle' => $bucket['subtitle'],
                'requests' => $bucket['requests']->all(),
                'totals' => $this->totalsFor($bucket['requests']),
            ])
            ->sortBy(fn (array $entry) => [$entry['kind'] === 'driver' ? 0 : 1, $entry['title']])
            ->values();
    }

    /**
     * @return array{number: string, destination: string, address: ?string, city: ?string, notes: ?string, lines: list<array{product: string, quantity: float, unit: string}>}
     */
    private function requestPayload(ProductionOrderRequest $request, Location|Customer|null $destination, Location $orderLocation): array
    {
        $pickupAtLocation = $destination instanceof Customer && $destination->deliveryPerson === null;

        return [
            'number' => $request->numberLabel(),
            'destination' => $destination?->name ?? '—',
            'address' => $destination?->address,
            'city' => $destination?->city,
            'notes' => $pickupAtLocation
                ? trim('Retira en '.$orderLocation->name.($request->notes ? ' · '.$request->notes : ''))
                : $request->notes,
            'lines' => $request->lines
                ->map(fn ($line) => [
                    'product' => $line->product?->name ?? '—',
                    'quantity' => (float) $line->quantity,
                    'unit' => $line->unit->short(),
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requests
     * @return list<array{product: string, quantity: float, unit: string}>
     */
    private function totalsFor(Collection $requests): array
    {
        return $requests
            ->flatMap(fn (array $request) => $request['lines'])
            ->groupBy(fn (array $line) => $line['product'].'|'.$line['unit'])
            ->map(fn (Collection $lines) => [
                'product' => $lines->first()['product'],
                'quantity' => $lines->sum('quantity'),
                'unit' => $lines->first()['unit'],
            ])
            ->sortBy('product')
            ->values()
            ->all();
    }

    /**
     * @return array{number: string, type: string, date: ?string, generated_at: string}
     */
    private function meta(ProductionOrder $order): array
    {
        return [
            'number' => $order->numberLabel(),
            'type' => $order->type->label(),
            'date' => $order->scheduled_for?->format('d/m/Y'),
            'generated_at' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }
}

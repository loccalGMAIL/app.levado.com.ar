<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryDestinationType;
use App\Http\Requests\StoreInstantProductionOrderRequest;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Orden instantánea: un solo paso — destino único + artículos/cantidad —
 * que crea la orden, la confirma y la produce en el mismo request. Reemplaza
 * a "Producir suelto" (ProductionController::create/store, retirado):
 * absorbido acá dentro del concepto de orden de producción, numerada como
 * cualquier otra. Vive como modal en production-orders/index.blade.php
 * (modals/instant-create.blade.php) — no tiene pantalla propia, ProductionOrderController::index()
 * ya junta $locations/$deliveryPeople/$products para el modal "+ Nuevo pedido"
 * y el mismo juego de datos le sirve a éste.
 */
class InstantProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    /**
     * Preview del consumo combinado, sin destino (no afecta el costo) y sin
     * persistir nada — mismo endpoint conceptual que production.preview,
     * pero para varios renglones a la vez.
     */
    public function preview(Request $request): JsonResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validate(StoreInstantProductionOrderRequest::itemRules($tenant));

        return response()->json($this->orders->previewFor(
            $this->pairsFrom($tenant, $data['items']),
            $tenant->defaultLocation(),
        ));
    }

    public function store(StoreInstantProductionOrderRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        $order = $this->orders->produceInstant(
            $tenant,
            $request->user(),
            DeliveryDestinationType::from($data['destination_type']),
            (int) $data['destination_id'],
            $this->pairsFrom($tenant, $data['items']),
            $data['notes'] ?? null,
        );

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'production_order',
            targetId: $order->id,
            action: 'production_order.instant_produced',
            payload: ['type' => $order->type->value],
            tenantId: $tenant->id,
        );

        return redirect()->route('production-orders.show', $order)->with('status', 'Orden producida.');
    }

    /**
     * Agrupa los renglones del form por artículo — dos renglones del mismo
     * artículo se suman en una sola producción, mismo criterio que
     * ProductionOrderService::aggregate().
     *
     * @param  array<int, array{product_id: int, quantity: float}>  $items
     * @return Collection<int, array{product: Product, quantity: float}>
     */
    private function pairsFrom(Tenant $tenant, array $items): Collection
    {
        // with('recipe'): baseConsumption()/guardProducible() lo leen enseguida
        // (factorFor(), explodeWithLabor()) y preventLazyLoading no perdona.
        $products = $tenant->products()->producible()
            ->with('recipe')
            ->whereIn('id', collect($items)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return collect($items)
            ->groupBy('product_id')
            ->map(fn (Collection $group, string $productId) => [
                'product' => $products[(int) $productId],
                'quantity' => (float) $group->sum('quantity'),
            ])
            ->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryDestinationType;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use App\Services\ProductionOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de los pedidos de una orden de producción: uno por destino (sucursal
 * o repartidor). El binding {productionOrderRequest} usa scoped bindings —
 * un pedido de otra orden resuelve 404 (ver ProductionOrder::productionOrderRequests()).
 */
class ProductionOrderRequestController extends Controller
{
    public function __construct(private readonly ProductionOrderService $orders) {}

    public function store(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('update', $productionOrder);
        abort_unless($productionOrder->isEditable(), 422, 'La orden ya no se puede editar.');

        $data = $request->validate([
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'destination_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = app(Tenant::class);
        $type = DeliveryDestinationType::from($data['destination_type']);

        $destinationExists = match ($type) {
            DeliveryDestinationType::Location => $tenant->locations()->whereKey($data['destination_id'])->exists(),
            DeliveryDestinationType::DeliveryPerson => $tenant->deliveryPeople()->whereKey($data['destination_id'])->exists(),
        };
        abort_unless($destinationExists, 422, 'El destino elegido no es válido.');

        $this->orders->addRequest($productionOrder, [
            'destination_type' => $type->value,
            'destination_id' => $data['destination_id'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Pedido agregado.');
    }

    public function destroy(ProductionOrder $productionOrder, ProductionOrderRequest $productionOrderRequest): RedirectResponse
    {
        $this->authorize('update', $productionOrder);
        abort_unless($productionOrder->isEditable(), 422, 'La orden ya no se puede editar.');

        // cascadeOnDelete se lleva las líneas del pedido con él.
        $productionOrderRequest->delete();

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Pedido eliminado.');
    }
}

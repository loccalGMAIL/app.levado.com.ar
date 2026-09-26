<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryDestinationType;
use App\Http\Requests\StorePlaceRequestRequest;
use App\Models\Tenant;
use App\Rules\ValidDestination;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Alta de un pedido suelto, sin pasar por crear una orden: el panadero carga
 * destino + fecha + artículos y ProductionOrderService::placeRequest()
 * encuentra-o-crea la orden del día sola. Distinto de
 * ProductionOrderRequestController, que agrega un pedido a una orden que ya
 * está abierta.
 */
class ProductionRequestController extends Controller
{
    public function __construct(private readonly ProductionOrderService $orders) {}

    public function store(StorePlaceRequestRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        $productionRequest = $this->orders->placeRequest($tenant, $data, $request->user());
        $order = $productionRequest->productionOrder;

        return redirect()
            ->route('production-orders.show', $order)
            ->with('status', "{$productionRequest->numberLabel()} cargado en la {$order->numberLabel()}.");
    }

    /**
     * Renglones del último pedido a un destino, para precargar la grilla del
     * modal de alta *antes* de que el pedido nuevo exista. No persiste nada.
     */
    public function previousLines(Request $request): JsonResponse
    {
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'destination_id' => ['required', 'integer', new ValidDestination($tenant, $request->input('destination_type'))],
        ]);

        $previous = $this->orders->previousRequestForDestination(
            $tenant,
            DeliveryDestinationType::from($data['destination_type']),
            $data['destination_id'],
        );

        return response()->json($this->orders->previousLinesPayload($tenant, $previous));
    }
}

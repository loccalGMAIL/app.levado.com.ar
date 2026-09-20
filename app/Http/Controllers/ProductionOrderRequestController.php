<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryDestinationType;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use App\Rules\ValidDestination;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
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

        $tenant = app(Tenant::class);

        // El select unificado de destino manda "location:3"/"customer:7" —
        // se parte al mismo par que valida el resto del dominio (ver
        // DeliveryDestinationType::splitRef(), dueño único del split).
        if ($request->filled('destination') && ! $request->filled('destination_type')) {
            [$type, $id] = DeliveryDestinationType::splitRef($request->string('destination')->toString());
            $request->merge(['destination_type' => $type, 'destination_id' => $id]);
        }

        $data = $request->validate([
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'destination_id' => ['required', 'integer', new ValidDestination($tenant, $request->input('destination_type'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->orders->addRequest($productionOrder, [
            'destination_type' => $data['destination_type'],
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

    /**
     * Renglones del último pedido al mismo destino, para precargar la
     * grilla ("Traer del pedido anterior"). No persiste nada — es sólo una
     * lectura que el usuario revisa y ajusta antes de Guardar (que sí pasa
     * por el único camino de escritura: ProductionOrderLineController::sync()).
     */
    public function previousLines(ProductionOrder $productionOrder, ProductionOrderRequest $productionOrderRequest): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        $previous = $this->orders->previousRequestFor($productionOrderRequest);

        return response()->json($this->orders->previousLinesPayload(app(Tenant::class), $previous));
    }
}

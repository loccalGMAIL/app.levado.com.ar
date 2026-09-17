<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryDestinationType;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
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

        if ($previous === null) {
            return response()->json(['found' => false]);
        }

        // Filtrado server-side: un artículo del pedido anterior que dejó de
        // ser producible (recategorizado) no se trae — si se trajera igual,
        // el PUT de guardado fallaría con un 422 por renglón sin que el
        // usuario entienda por qué.
        $producibleIds = app(Tenant::class)->products()->producible()
            ->whereIn('id', $previous->lines->pluck('product_id'))
            ->pluck('id');

        [$lines, $skipped] = $previous->lines->partition(fn ($line) => $producibleIds->contains($line->product_id));

        return response()->json([
            'found' => true,
            'source' => [
                'label' => $previous->productionOrder->numberLabel().' · '.$previous->numberLabel(),
                'scheduled_for' => $previous->productionOrder->scheduled_for?->format('d/m/Y'),
            ],
            'lines' => $lines->values()->map(fn ($line) => [
                'product_id' => $line->product_id,
                'name' => $line->product->name,
                'unit' => $line->unit->short(),
                'quantity' => (float) $line->quantity,
            ]),
            'skipped' => $skipped->map(fn ($line) => $line->product?->name ?? '—')->values(),
        ]);
    }
}

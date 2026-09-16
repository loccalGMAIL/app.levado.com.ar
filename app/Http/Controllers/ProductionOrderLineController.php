<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncProductionOrderLinesRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;

/**
 * Único camino de escritura de las líneas de un pedido (artículo + cantidad):
 * un solo PUT reemplaza el set completo, para que la grilla pueda guardar
 * "agregué tres, edité dos cantidades y borré una" en un viaje atómico. Antes
 * existían store()/destroy() por línea, con recarga de página y sin poder
 * editar una cantidad ya cargada — retirados (ver ProductionOrderService::syncLines()).
 */
class ProductionOrderLineController extends Controller
{
    public function __construct(private readonly ProductionOrderService $orders) {}

    public function sync(
        SyncProductionOrderLinesRequest $request,
        ProductionOrder $productionOrder,
        ProductionOrderRequest $productionOrderRequest,
    ): JsonResponse {
        $this->authorize('update', $productionOrder);
        abort_unless($productionOrder->isEditable(), 422, 'La orden ya no se puede editar.');

        $this->orders->syncLines($productionOrderRequest, $request->validated()['lines']);

        // Todo lo que la pantalla tiene que repintar en un solo viaje: las
        // líneas persistidas, el total agregado de la orden y el consumo de
        // insumos — evita encadenar fetch tras guardar.
        return response()->json([
            'lines' => $productionOrderRequest->fresh()->lines()->with('product')->orderBy('position')->get()
                ->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'name' => $line->product->name,
                    'unit' => $line->unit->short(),
                    'quantity' => (float) $line->quantity,
                ]),
            'aggregated' => $this->orders->aggregate($productionOrder)->map(fn (array $entry) => [
                'product_id' => $entry['product']->id,
                'name' => $entry['product']->name,
                'unit' => $entry['product']->unit->short(),
                'quantity' => $entry['quantity'],
            ]),
            'preview' => $this->orders->preview($productionOrder->load('location')),
        ]);
    }
}

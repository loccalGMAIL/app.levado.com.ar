<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD de las líneas (artículo + cantidad) de un pedido. Igual que
 * ProductionOrderRequestController, el binding {line} usa scoped bindings
 * de a dos niveles (production_order -> production_order_request -> line).
 */
class ProductionOrderLineController extends Controller
{
    public function store(Request $request, ProductionOrder $productionOrder, ProductionOrderRequest $productionOrderRequest): RedirectResponse
    {
        $this->authorize('update', $productionOrder);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        // producible(): mismo filtro que la pantalla "producir ahora" — sólo
        // elaborados activos, con receta, en una categoría "se produce".
        $product = app(Tenant::class)->products()->producible()->find($data['product_id']);
        abort_if($product === null, 422, 'El artículo no está disponible para producir.');

        $productionOrderRequest->lines()->create([
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
            'unit' => $product->unit->value,
        ]);

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Artículo agregado.');
    }

    public function destroy(ProductionOrder $productionOrder, ProductionOrderRequest $productionOrderRequest, ProductionOrderLine $line): RedirectResponse
    {
        $this->authorize('update', $productionOrder);

        $line->delete();

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Artículo quitado.');
    }
}

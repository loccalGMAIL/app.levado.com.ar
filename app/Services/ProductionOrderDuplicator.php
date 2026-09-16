<?php

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copia profunda de una orden de producción (pedidos + líneas), usada tanto
 * para "repetir esta orden" como para instanciar una plantilla — es la misma
 * operación con distinto destino: una orden normal fechada, o una plantilla
 * (is_template=true, sin fecha). La copia siempre nace en Draft y sin ningún
 * timestamp de ciclo de vida; editarla nunca toca el origen.
 */
class ProductionOrderDuplicator
{
    public function __construct(private ProductionOrderService $orders) {}

    public function duplicate(
        ProductionOrder $source,
        ?User $user = null,
        ?string $scheduledFor = null,
        ?string $templateName = null,
    ): ProductionOrder {
        return DB::transaction(function () use ($source, $user, $scheduledFor, $templateName) {
            $isTemplate = $templateName !== null;

            $copy = $this->orders->createOrder(Tenant::findOrFail($source->tenant_id), [
                'location_id' => $source->location_id,
                'type' => $source->type->value,
                'scheduled_for' => $isTemplate ? null : $scheduledFor,
                'status' => ProductionOrderStatus::Draft->value,
                'is_template' => $isTemplate,
                'name' => $templateName,
                'notes' => $source->notes,
                'user_id' => $user?->id,
            ]);

            foreach ($source->productionOrderRequests()->with('lines')->get() as $request) {
                // El número de pedido (position) se recalcula desde cero en la
                // copia — no se copia el del origen: la copia arranca su
                // propia numeración 1, 2, 3... y con el unique nuevo copiar el
                // valor viejo podría además colisionar entre pedidos.
                $newRequest = $this->orders->addRequest($copy, [
                    'destination_type' => $request->destination_type->value,
                    'destination_id' => $request->destination_id,
                    'notes' => $request->notes,
                ]);

                foreach ($request->lines as $line) {
                    $newRequest->lines()->create([
                        'product_id' => $line->product_id,
                        'quantity' => $line->quantity,
                        'unit' => $line->unit->value,
                        'position' => $line->position,
                    ]);
                }
            }

            return $copy;
        });
    }
}

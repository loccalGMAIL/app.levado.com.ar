<?php

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
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
    public function duplicate(
        ProductionOrder $source,
        ?User $user = null,
        ?string $scheduledFor = null,
        ?string $templateName = null,
    ): ProductionOrder {
        return DB::transaction(function () use ($source, $user, $scheduledFor, $templateName) {
            $isTemplate = $templateName !== null;

            $copy = ProductionOrder::create([
                'tenant_id' => $source->tenant_id,
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
                $newRequest = $copy->productionOrderRequests()->create([
                    'tenant_id' => $source->tenant_id,
                    'destination_type' => $request->destination_type->value,
                    'destination_id' => $request->destination_id,
                    'notes' => $request->notes,
                    'position' => $request->position,
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

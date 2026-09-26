<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * CRUD del molde de un pedido recurrente. Separado de ProductionOrderService
 * (que ya tiene su responsabilidad clara: el motor de la orden) — esto es
 * dominio propio, con su propia pantalla de administración.
 */
class RecurringProductionRequestService
{
    /**
     * @param  array{
     *     destination_type: string, destination_id: int,
     *     weekdays: array<int, int>, starts_on: string, ends_on?: ?string,
     *     notes?: ?string,
     *     lines?: array<int, array{product_id: int, quantity: float|string}>,
     * }  $attributes
     */
    public function create(Tenant $tenant, array $attributes, ?User $user = null): RecurringProductionRequest
    {
        return DB::transaction(function () use ($tenant, $attributes, $user) {
            $recurring = $tenant->recurringProductionRequests()->create([
                'destination_type' => $attributes['destination_type'],
                'destination_id' => $attributes['destination_id'],
                // array_map('intval'): weekdays llega desde un formulario
                // HTML (strings, ej. "3") — occursOn() compara con
                // dayOfWeekIso (int) en modo estricto, así que un string sin
                // castear no matchearía nunca.
                'weekdays' => array_map('intval', $attributes['weekdays']),
                'starts_on' => $attributes['starts_on'],
                'ends_on' => $attributes['ends_on'] ?? null,
                'active' => true,
                'notes' => $attributes['notes'] ?? null,
                'user_id' => $user?->id,
            ]);

            $this->replaceLines($recurring, $attributes['lines'] ?? []);

            return $recurring;
        });
    }

    /**
     * @param  array{
     *     weekdays?: array<int, int>, starts_on?: string, ends_on?: ?string,
     *     notes?: ?string, lines?: array<int, array{product_id: int, quantity: float|string}>,
     * }  $attributes
     */
    public function update(RecurringProductionRequest $recurring, array $attributes): void
    {
        DB::transaction(function () use ($recurring, $attributes) {
            if (isset($attributes['weekdays'])) {
                $attributes['weekdays'] = array_map('intval', $attributes['weekdays']);
            }

            $recurring->update(array_intersect_key($attributes, array_flip(['weekdays', 'starts_on', 'ends_on', 'notes'])));

            if (array_key_exists('lines', $attributes)) {
                $this->replaceLines($recurring, $attributes['lines']);
            }
        });
    }

    public function toggleActive(RecurringProductionRequest $recurring): void
    {
        $recurring->update(['active' => ! $recurring->active]);
    }

    /**
     * Las líneas del molde en el mismo shape que espera
     * ProductionOrderService::placeRequest()/syncLines() — para copiarlas a
     * cada instancia nueva sin duplicar la forma del array en dos lugares.
     *
     * @return array<int, array{product_id: int, quantity: float}>
     */
    public function snapshotLines(RecurringProductionRequest $recurring): array
    {
        return $recurring->lines->map(fn ($line) => [
            'product_id' => $line->product_id,
            'quantity' => (float) $line->quantity,
        ])->all();
    }

    /**
     * Reemplaza el set completo de líneas del molde — mismo criterio que
     * ProductionOrderService::syncLines() pero más simple: acá no hace
     * falta preservar ids (las líneas del molde no se referencian desde
     * afuera), así que un delete+create alcanza.
     *
     * @param  array<int, array{product_id: int, quantity: float|string}>  $lines
     */
    private function replaceLines(RecurringProductionRequest $recurring, array $lines): void
    {
        $productIds = collect($lines)->pluck('product_id')->unique();
        $products = Product::query()->producible()->whereIn('id', $productIds)->get()->keyBy('id');

        $recurring->lines()->delete();

        foreach (array_values($lines) as $i => $line) {
            $product = $products->get($line['product_id']);

            if ($product === null) {
                continue;
            }

            $recurring->lines()->create([
                'product_id' => $product->id,
                'quantity' => $line['quantity'],
                'unit' => $product->unit->value,
                'position' => $i + 1,
            ]);
        }
    }
}

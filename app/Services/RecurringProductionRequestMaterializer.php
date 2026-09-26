<?php

namespace App\Services;

use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Genera por adelantado las instancias de los pedidos recurrentes activos,
 * sin cron (el hosting no corre schedule:run todavía): se llama al abrir
 * Órdenes (materializeIfDue()), con un horizonte corto y autocurativo — si
 * nadie entra un par de días, se pone al día apenas alguien vuelve a entrar.
 */
class RecurringProductionRequestMaterializer
{
    /** Días hacia adelante que se generan por adelantado (incluye hoy). */
    public const HORIZON_DAYS = 7;

    public function __construct(private ProductionOrderService $orders) {}

    /**
     * A lo sumo una corrida por hora por negocio. Columna, no
     * Cache::remember(): sobrevive a un deploy/config:clear y es el único
     * dato observable de que la feature está viva. El Cache::lock cubre
     * sólo la ventana de dos pestañas abriendo el listado en el mismo
     * segundo — perder esa carrera es gratis, el throttle real es la columna.
     * Nunca propaga: es código que corre en el index() de cualquiera.
     */
    public function materializeIfDue(Tenant $tenant): void
    {
        if ($tenant->recurring_materialized_at?->gt(now()->subHour())) {
            return;
        }

        $lock = Cache::lock("recurring-materialize:{$tenant->id}", 30);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->materialize($tenant);
            $tenant->forceFill(['recurring_materialized_at' => now()])->save();
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Genera las instancias que falten para los recurrentes activos del
     * negocio, hasta $horizonDays hacia adelante (incluye hoy). Idempotente:
     * correrlo dos veces no duplica nada — el set de existencia se arma con
     * una sola consulta (join, sin N+1) y el unique de la base es la red.
     *
     * @return int cantidad de instancias generadas
     */
    public function materialize(Tenant $tenant, ?int $horizonDays = null): int
    {
        // user: aunque sea null (belongsTo con FK null no evita el chequeo de
        // preventLazyLoading — corta antes de mirar si haría falta query).
        $recurring = $tenant->recurringProductionRequests()->active()->with(['lines', 'destination', 'user'])->get();

        if ($recurring->isEmpty()) {
            return 0;
        }

        $dates = CarbonPeriod::create(now()->startOfDay(), now()->startOfDay()->addDays(($horizonDays ?? self::HORIZON_DAYS) - 1));
        $existing = $this->existingInstances($tenant, $dates->getStartDate(), $dates->getEndDate());

        $generated = 0;

        foreach ($recurring as $r) {
            // Destino dado de baja: no se generan pedidos hacia un lugar que
            // ya no existe operativamente. Se saltea en silencio — no es un
            // error del recurrente, es un estado válido del negocio.
            if (! ($r->destination?->active ?? false)) {
                continue;
            }

            foreach ($dates as $date) {
                if (! $r->occursOn($date)) {
                    continue;
                }

                if ($existing->contains("{$r->id}:{$date->toDateString()}")) {
                    continue;
                }

                try {
                    $this->orders->placeRequest($tenant, [
                        'destination_type' => $r->destination_type->value,
                        'destination_id' => $r->destination_id,
                        'scheduled_for' => $date->toDateString(),
                        'notes' => $r->notes,
                        'lines' => $r->lines->map(fn ($line) => [
                            'product_id' => $line->product_id,
                            'quantity' => (float) $line->quantity,
                        ])->all(),
                        'recurring_production_request_id' => $r->id,
                    ], $r->user);

                    $generated++;
                } catch (Throwable $e) {
                    // Un recurrente roto (artículo que dejó de ser producible,
                    // etc.) no debe tumbar la generación del resto.
                    report($e);
                }
            }
        }

        return $generated;
    }

    /**
     * Qué (recurrente, fecha) ya tiene una instancia en la ventana — una
     * sola query para toda la corrida, no una por (recurrente, fecha). El
     * join es crudo y NO aplica el global scope ExcludeTemplatesScope de
     * ProductionOrder (al revés de previousRequestFor(), que sí lo aplica
     * vía whereHas) — por eso el is_template=false explícito.
     *
     * @return Collection<int, string> claves "{recurringId}:{fecha}"
     */
    private function existingInstances(Tenant $tenant, Carbon $from, Carbon $to): Collection
    {
        return ProductionOrderRequest::withTrashed()
            ->where('production_order_requests.tenant_id', $tenant->id)
            ->whereNotNull('recurring_production_request_id')
            ->join('production_orders', 'production_orders.id', '=', 'production_order_requests.production_order_id')
            ->where('production_orders.is_template', false)
            ->whereBetween('production_orders.scheduled_for', [$from->toDateString(), $to->toDateString()])
            ->get([
                'production_order_requests.recurring_production_request_id as rid',
                'production_orders.scheduled_for as date',
            ])
            ->map(fn ($row) => $row->rid.':'.Carbon::parse($row->date)->toDateString());
    }
}

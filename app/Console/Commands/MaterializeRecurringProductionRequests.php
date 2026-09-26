<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RecurringProductionRequestMaterializer;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;

/**
 * Generación manual de pedidos recurrentes — hoy es la única vía además de
 * "al entrar a Órdenes" (materializeIfDue(), throttleado a una vez por hora).
 * El día que el hosting tenga cron, registrar esto en Kernel.php es la única
 * línea que falta para dejar de depender de que alguien abra la pantalla.
 */
class MaterializeRecurringProductionRequests extends Command
{
    protected $signature = 'production:materialize-recurring {--tenant= : Limitar a un tenant por id}';

    protected $description = 'Genera por adelantado las instancias de los pedidos recurrentes activos.';

    public function handle(RecurringProductionRequestMaterializer $materializer): int
    {
        $tenantId = $this->option('tenant');

        $tenants = Tenant::query()
            ->active()
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->get();

        $total = 0;

        foreach ($tenants as $tenant) {
            $generated = $materializer->materialize($tenant);
            $total += $generated;

            if ($generated > 0) {
                info("{$tenant->name}: {$generated} pedido(s) generado(s).");
            }
        }

        info("Listo. {$total} pedido(s) generado(s) en total.");

        return self::SUCCESS;
    }
}

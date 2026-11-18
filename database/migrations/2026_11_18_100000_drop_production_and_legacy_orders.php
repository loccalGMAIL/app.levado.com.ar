<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.23.0: se retira por completo el módulo de producción (producir, orden
 * instantánea, historial de fabricaciones) y la capa de pedidos vieja (órdenes
 * diarias → pedidos por destino → líneas, y pedidos recurrentes). Las
 * planillas semanales de pedidos arrancan de cero (order_sheets*).
 *
 * El usuario confirmó que ninguna de estas tablas tiene datos reales, así que
 * se borran sin migrar nada. Guarda de seguridad: si hubiera movimientos de
 * stock generados por una producción, se aborta antes de dejar el ledger con
 * referencias huérfanas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('stock_movements')->where('reference_type', 'production')->count();

        if ($orphans > 0) {
            throw new RuntimeException("Hay {$orphans} movimientos de stock generados por producciones; revisalos antes de borrar el módulo.");
        }

        // Orden por FK: productions → production_order_lines → production_order_requests
        // (apunta a recurring_production_requests) → recurrentes → production_orders.
        foreach ([
            'productions',
            'production_order_lines',
            'production_order_requests',
            'recurring_production_request_lines',
            'recurring_production_requests',
            'production_orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'next_production_order_number',
                'next_production_order_request_number',
                'recurring_materialized_at',
            ]);
        });
    }

    /**
     * No reversible: las tablas se crearon en migraciones históricas que
     * referencian modelos que ya no existen, y no tenían datos que recuperar.
     */
    public function down(): void
    {
        throw new RuntimeException('Migración irreversible: el módulo de producción fue retirado en v0.23.0.');
    }
};

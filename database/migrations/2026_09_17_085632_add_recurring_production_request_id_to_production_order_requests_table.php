<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula una instancia real (ProductionOrderRequest) a su molde
     * recurrente. nullOnDelete: el recurrente nunca se borra físico (baja
     * lógica con `active`), pero si algún día se borrara, la historia real
     * no debe irse con él.
     *
     * El unique (recurring_production_request_id, production_order_id) es
     * la garantía de "no duplicar" a nivel base: como "misma fecha" ⇒
     * "misma orden" (por construcción de ProductionOrderService::orderForDate()),
     * un recurrente no puede tener dos instancias en la MISMA orden. MySQL
     * admite múltiples NULL, así que los pedidos cargados a mano no lo ven.
     */
    public function up(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            // Nombre de constraint explícito y corto: el que autogenera
            // Laravel para esta columna larga excede los 64 caracteres que
            // permite MySQL (error 1059) — mismo motivo que en
            // create_recurring_production_request_lines_table.
            $table->foreignId('recurring_production_request_id')->nullable()->after('production_order_id')
                ->constrained(indexName: 'production_order_requests_recurring_id_fk')
                ->nullOnDelete();

            $table->index(['tenant_id', 'recurring_production_request_id'], 'production_order_requests_recurring_index');
            $table->unique(
                ['recurring_production_request_id', 'production_order_id'],
                'production_order_requests_recurring_order_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->dropUnique('production_order_requests_recurring_order_unique');
            $table->dropIndex('production_order_requests_recurring_index');
            // dropForeign(nombre): no dropConstrainedForeignId(), que
            // recalcularía el nombre largo por convención (nunca existió,
            // se creó con el nombre corto explícito de arriba).
            $table->dropForeign('production_order_requests_recurring_id_fk');
            $table->dropColumn('recurring_production_request_id');
        });
    }
};

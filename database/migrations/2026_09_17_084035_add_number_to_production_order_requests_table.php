<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numera los pedidos por negocio ("Pedido #123"), independiente del
     * `position` local a su orden ("Pedido 1", "Pedido 2"...). Mismo motivo
     * que el número de orden: el panadero va a cargar pedidos sueltos y
     * necesita una identidad propia para cada uno, no relativa a la orden.
     *
     * Orden de pasos igual que production_orders.number (no cambiar):
     * 1) columnas, 2) backfill contra los datos reales ya existentes,
     * 3) unique — si el unique va antes del backfill, aborta apenas un
     * tenant tenga más de un pedido (todos arrancan sin número).
     *
     * Los pedidos de órdenes plantilla quedan en NULL (no consumen número,
     * igual que las plantillas mismas con production_orders.number).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('next_production_order_request_number')->default(1)->after('next_production_order_number');
        });

        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('position');
        });

        $tenantIds = DB::table('production_order_requests')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $requestIds = DB::table('production_order_requests')
                ->join('production_orders', 'production_orders.id', '=', 'production_order_requests.production_order_id')
                ->where('production_order_requests.tenant_id', $tenantId)
                ->where('production_orders.is_template', false)
                ->orderBy('production_order_requests.id')
                ->pluck('production_order_requests.id');

            $number = 1;
            foreach ($requestIds as $requestId) {
                DB::table('production_order_requests')->where('id', $requestId)->update(['number' => $number]);
                $number++;
            }

            DB::table('tenants')->where('id', $tenantId)->update(['next_production_order_request_number' => $number]);
        }

        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->unique(['tenant_id', 'number'], 'production_order_requests_tenant_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->dropUnique('production_order_requests_tenant_number_unique');
            $table->dropColumn('number');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('next_production_order_request_number');
        });
    }
};

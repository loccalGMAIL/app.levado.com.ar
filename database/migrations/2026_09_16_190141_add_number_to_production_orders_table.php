<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numera las órdenes de producción por negocio ("Orden #7"). Las
     * plantillas (is_template=true) no consumen número: quedan con NULL.
     *
     * Orden de pasos, no cambiar: 1) columnas, 2) backfill contra los datos
     * reales ya existentes, 3) unique — si el unique va antes del backfill,
     * la migración aborta apenas hay más de una orden en un tenant (todas
     * arrancan sin número).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('next_production_order_number')->default(1)->after('productive_hours_month');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('type');
        });

        $tenantIds = DB::table('production_orders')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $orderIds = DB::table('production_orders')
                ->where('tenant_id', $tenantId)
                ->where('is_template', false)
                ->orderBy('id')
                ->pluck('id');

            $number = 1;
            foreach ($orderIds as $orderId) {
                DB::table('production_orders')->where('id', $orderId)->update(['number' => $number]);
                $number++;
            }

            DB::table('tenants')->where('id', $tenantId)->update(['next_production_order_number' => $number]);
        }

        Schema::table('production_orders', function (Blueprint $table) {
            $table->unique(['tenant_id', 'number'], 'production_orders_tenant_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropUnique('production_orders_tenant_number_unique');
            $table->dropColumn('number');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('next_production_order_number');
        });
    }
};

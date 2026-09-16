<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `position` ya existe (unsignedInteger, default 0) pero está muerta:
     * nadie la incrementa hoy, así que todas las filas reales valen 0. Se
     * reusa como el número de pedido dentro de su orden ("Pedido 1", "Pedido
     * 2"...). Backfill primero, SIEMPRE antes del unique — con datos reales
     * hoy en 0, el unique sin backfill aborta apenas una orden tenga 2 pedidos.
     */
    public function up(): void
    {
        $orderIds = DB::table('production_order_requests')->distinct()->pluck('production_order_id');

        foreach ($orderIds as $orderId) {
            $requestIds = DB::table('production_order_requests')
                ->where('production_order_id', $orderId)
                ->orderBy('id')
                ->pluck('id');

            $position = 1;
            foreach ($requestIds as $requestId) {
                DB::table('production_order_requests')->where('id', $requestId)->update(['position' => $position]);
                $position++;
            }
        }

        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->unique(['production_order_id', 'position'], 'production_order_requests_order_position_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->dropUnique('production_order_requests_order_position_unique');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baja lógica para pedidos (antes era DELETE físico, una desviación de
     * la convención del proyecto). Se vuelve necesaria, no sólo prolija, con
     * la recurrencia: si el materializador no puede distinguir "nunca se
     * generó" de "se generó y lo borraron a propósito", regenera lo borrado
     * en la próxima corrida. Con soft delete, el set de existencia se arma
     * withTrashed() y un pedido borrado cuenta como "ya generado".
     */
    public function up(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_order_requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

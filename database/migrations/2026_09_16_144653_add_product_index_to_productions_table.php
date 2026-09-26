<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            // El historial de fabricaciones del artículo y el baseline de la
            // alerta de salto de costo consultan siempre por product_id ordenado
            // por produced_at (ver ProductCostHistoryController y ProductionService).
            $table->index(['product_id', 'produced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'produced_at']);
        });
    }
};

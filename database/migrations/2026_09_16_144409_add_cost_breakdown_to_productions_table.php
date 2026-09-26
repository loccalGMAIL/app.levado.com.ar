<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `unit_cost`/`total_cost` pasan a incluir mano de obra (antes, solo
     * insumos físicos — ver comentario desactualizado en la migración
     * original de `productions`). Se agrega el desglose para no perder esa
     * distinción.
     */
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('material_cost', 14, 4)->default(0)->after('unit');
            $table->decimal('labor_cost', 14, 4)->default(0)->after('material_cost');
        });

        // Backfill honesto: las producciones anteriores a este cambio eran
        // efectivamente insumos-only (el exploder ignoraba la mano de obra).
        DB::table('productions')->update([
            'material_cost' => DB::raw('total_cost'),
            'labor_cost' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn(['material_cost', 'labor_cost']);
        });
    }
};

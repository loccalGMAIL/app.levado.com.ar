<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opcional por receta: si lo que rinde se pide en bandejas o latas, y
     * cuánto entra en una (en la unidad de rendimiento de la receta). Lo
     * comparten el artículo base y sus presentaciones.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('container_type', 20)->nullable()->after('yield_unit');
            $table->decimal('units_per_container', 10, 3)->nullable()->after('container_type');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['container_type', 'units_per_container']);
        });
    }
};

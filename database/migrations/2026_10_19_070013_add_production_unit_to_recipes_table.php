<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            // Unidad de producción: cómo se tanda esta receta en el obrador
            // ("lata" = 72 u). Ambas null = vale el rinde completo ("tanda").
            $table->string('production_unit_name', 30)->nullable()->after('yield_unit');
            $table->decimal('production_unit_quantity', 12, 3)->nullable()->after('production_unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['production_unit_name', 'production_unit_quantity']);
        });
    }
};

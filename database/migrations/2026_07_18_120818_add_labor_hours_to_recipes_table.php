<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache de horas de mano de obra propias de la receta (suma de labor_lines),
     * mantenido por RecipeCostPropagator junto a unit_cost. Permite que el
     * dashboard calcule costo+overhead y ordene/pagine en SQL sin recalcular
     * cada receta en PHP. Backfill: `php artisan recipes:refresh-costs`.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('labor_hours', 8, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('labor_hours');
        });
    }
};

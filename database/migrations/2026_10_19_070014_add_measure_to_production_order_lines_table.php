<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_lines', function (Blueprint $table) {
            // 'unit' = unidades del artículo; 'production_unit' = unidades de
            // producción de su receta. Las filas viejas quedan en 'unit'.
            $table->string('measure', 16)->default('unit')->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_lines', function (Blueprint $table) {
            $table->dropColumn('measure');
        });
    }
};

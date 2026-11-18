<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medida en la que se guardaron las cantidades de la fila: null = unidades
     * del artículo; 'bandeja'/'lata' = cantidad de recipientes.
     */
    public function up(): void
    {
        Schema::table('order_sheet_lines', function (Blueprint $table) {
            $table->string('container_type', 20)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('order_sheet_lines', function (Blueprint $table) {
            $table->dropColumn('container_type');
        });
    }
};

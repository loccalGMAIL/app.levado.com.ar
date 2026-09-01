<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renglón sin cargo (obsequio, promoción o muestra de la distribuidora):
     * suma stock pero no imputa costo al catálogo.
     */
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });
    }
};

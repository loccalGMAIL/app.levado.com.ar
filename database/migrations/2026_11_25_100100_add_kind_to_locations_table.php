<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de ubicación: una sucursal (donde está el terminado y entran las
     * compras) o la cámara de leudado (donde espera el crudo hasta el horno).
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('kind', 30)->default('store')->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Orden de recorrido del cliente dentro de la ruta de su repartidor. */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('route_position')->nullable()->after('delivery_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('route_position');
        });
    }
};

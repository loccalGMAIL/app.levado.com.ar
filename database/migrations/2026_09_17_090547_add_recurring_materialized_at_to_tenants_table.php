<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Throttle del materializador de recurrencia (sin cron: corre al abrir
     * Órdenes, a lo sumo una vez por hora por negocio) — columna y no
     * Cache::remember(), por dos motivos: sobrevive a un deploy/config:clear
     * (Hostinger compartido limpia el cache ahí), y es el único dato
     * observable de "la feature está viva sin cron" (se muestra en la
     * pantalla de recurrentes: "Última generación: hoy 08:14").
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('recurring_materialized_at')->nullable()->after('next_production_order_request_number');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('recurring_materialized_at');
        });
    }
};

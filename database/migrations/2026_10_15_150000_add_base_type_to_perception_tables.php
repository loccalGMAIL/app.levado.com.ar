<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada concepto define sobre qué base calcula (net_taxed, net_total o gross) y la
     * percepción de la compra guarda un snapshot. Lo existente venía calculando sobre
     * el neto gravado, que es el default.
     */
    public function up(): void
    {
        Schema::table('perception_concepts', function (Blueprint $table) {
            $table->string('base_type', 20)->default('net_taxed')->after('default_rate');
        });

        Schema::table('purchase_perceptions', function (Blueprint $table) {
            $table->string('base_type', 20)->default('net_taxed')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_perceptions', function (Blueprint $table) {
            $table->dropColumn('base_type');
        });

        Schema::table('perception_concepts', function (Blueprint $table) {
            $table->dropColumn('base_type');
        });
    }
};

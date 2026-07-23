<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Override del método de costeo de reventa; null → usa el default del negocio (tenant_settings).
            $table->string('costing_method', 20)->nullable()->after('cost_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });
    }
};

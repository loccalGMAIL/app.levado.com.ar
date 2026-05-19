<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('razon_social')->nullable()->after('name');
            $table->string('cuit', 20)->nullable()->after('razon_social');
            $table->string('condicion_iva', 20)->nullable()->after('cuit');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['razon_social', 'cuit', 'condicion_iva']);
        });
    }
};

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
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('default_iva_rate', 5, 4)->nullable()->after('invoice_total');
            $table->decimal('default_percepcion_rate', 5, 2)->nullable()->after('default_iva_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['default_iva_rate', 'default_percepcion_rate']);
        });
    }
};

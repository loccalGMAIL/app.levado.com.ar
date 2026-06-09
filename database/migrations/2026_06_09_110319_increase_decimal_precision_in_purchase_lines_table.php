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
        Schema::table('purchase_lines', function (Blueprint $table) {
            // decimal(10,4) only fits up to 999,999.9999 — insufficient for
            // real invoices where subtotals can exceed one million.
            $table->decimal('unit_price', 14, 4)->change();
            $table->decimal('subtotal', 14, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 4)->change();
            $table->decimal('subtotal', 10, 4)->change();
        });
    }
};

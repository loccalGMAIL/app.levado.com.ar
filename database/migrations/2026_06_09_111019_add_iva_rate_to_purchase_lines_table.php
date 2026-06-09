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
            // IVA alícuota aplicada a este renglón (0, 0.105 ó 0.21).
            $table->decimal('iva_rate', 5, 4)->default(0.21)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('iva_rate');
        });
    }
};

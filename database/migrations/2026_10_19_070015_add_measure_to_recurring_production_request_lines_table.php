<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_production_request_lines', function (Blueprint $table) {
            // Espejo de production_order_lines.measure.
            $table->string('measure', 16)->default('unit')->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_production_request_lines', function (Blueprint $table) {
            $table->dropColumn('measure');
        });
    }
};

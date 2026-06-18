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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->unsignedInteger('subdivisions')->nullable()->after('cost_per_unit');
            $table->string('subdivision_label', 50)->nullable()->after('subdivisions');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['subdivisions', 'subdivision_label']);
        });
    }
};

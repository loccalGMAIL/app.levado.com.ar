<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('fixed_cost_category_id')
                ->nullable()
                ->after('name')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->dropForeign(['fixed_cost_category_id']);
            $table->dropColumn('fixed_cost_category_id');
            $table->string('category')->default('otro');
        });
    }
};

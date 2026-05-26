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
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('is_semi_elaborate')->default(false)->after('active');
            $table->decimal('unit_cost', 10, 4)->nullable()->after('is_semi_elaborate');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['is_semi_elaborate', 'unit_cost']);
        });
    }
};

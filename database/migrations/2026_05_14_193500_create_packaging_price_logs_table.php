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
        Schema::create('packaging_price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_per_unit', 10, 4);
            $table->timestamp('recorded_at');

            $table->index('packaging_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packaging_price_logs');
    }
};

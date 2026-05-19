<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_cost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_cost_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_amount', 10, 2);
            $table->date('valid_from');

            $table->index(['fixed_cost_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_cost_logs');
    }
};

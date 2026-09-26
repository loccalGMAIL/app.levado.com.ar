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
        Schema::create('production_order_requests', function (Blueprint $table) {
            $table->id();
            // Lleva tenant_id propio (precedente product_cost_logs, no
            // recipe_subrecipe_lines): se lee desde la UI por fuera de su
            // orden — "qué lleva el repartidor Juan" — no sólo anidado.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->string('destination_type', 30);
            $table->unsignedBigInteger('destination_id');
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'destination_type', 'destination_id'], 'production_order_requests_destination_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_requests');
    }
};

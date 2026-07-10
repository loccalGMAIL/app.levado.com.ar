<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache derivado del ledger stock_movements: cantidad actual por ítem y sucursal.
     * Solo lo escribe StockService.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('stockable_type', 20); // 'ingredient' | 'packaging'
            $table->unsignedBigInteger('stockable_id');
            $table->decimal('quantity', 14, 4)->default(0); // en la unidad del ítem
            $table->decimal('min_quantity', 14, 4)->nullable(); // umbral de alerta
            $table->decimal('unit_cost', 14, 4)->default(0); // último costo de compra
            $table->timestamps();

            $table->unique(['tenant_id', 'stockable_type', 'stockable_id', 'location_id'], 'stock_levels_stockable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};

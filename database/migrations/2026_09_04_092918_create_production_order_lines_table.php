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
        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_request_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete: un artículo con líneas de pedido pendientes no
            // se puede borrar (mismo criterio que recipe_ingredient_lines).
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 10);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_lines');
    }
};

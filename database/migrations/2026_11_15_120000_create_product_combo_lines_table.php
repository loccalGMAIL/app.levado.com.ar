<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Componentes de un combo: el combo es un artículo (products.type = 'combo')
     * y cada línea dice cuánto de otro artículo lleva una unidad del combo.
     */
    public function up(): void
    {
        Schema::create('product_combo_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3); // en la unidad del componente
            $table->timestamps();

            $table->unique(['combo_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_combo_lines');
    }
};

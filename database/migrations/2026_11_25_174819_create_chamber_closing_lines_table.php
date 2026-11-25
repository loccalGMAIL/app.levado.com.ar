<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renglón de un cierre de cámara, en unidades del artículo base:
     * `expected_units` es el stock en cámara al abrir el cierre (snapshot);
     * `baked_units` = esperado − contado − merma, calculado al confirmar.
     */
    public function up(): void
    {
        Schema::create('chamber_closing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chamber_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('expected_units', 12, 3)->default(0);
            $table->decimal('counted_units', 12, 3)->default(0);
            $table->decimal('waste_units', 12, 3)->default(0);
            $table->string('waste_reason', 30)->nullable();
            $table->string('waste_note')->nullable();
            $table->decimal('baked_units', 12, 3)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();

            $table->unique(['chamber_closing_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamber_closing_lines');
    }
};

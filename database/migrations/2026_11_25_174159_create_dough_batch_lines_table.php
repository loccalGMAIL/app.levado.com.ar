<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renglón de un amasado: un artículo base, lo planeado, lo real y la
     * merma (con motivo). `goes_to_chamber` y `unit_cost` son snapshots que
     * se toman al confirmar. Las cantidades van en unidades del artículo base.
     */
    public function up(): void
    {
        Schema::create('dough_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dough_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('goes_to_chamber')->default(false);
            $table->decimal('planned_units', 12, 3)->default(0);
            $table->decimal('actual_units', 12, 3)->default(0);
            $table->decimal('waste_units', 12, 3)->default(0);
            $table->string('waste_reason', 30)->nullable();
            $table->string('waste_note')->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();

            $table->unique(['dough_batch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dough_batch_lines');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Masa (sub-receta semi-elaborada) de un amasado: lo planeado y lo que
     * sobró (+) o faltó (−), en la unidad de rinde de la sub-receta. Es solo
     * un registro: no mueve stock, porque no existe stock de masa.
     */
    public function up(): void
    {
        Schema::create('dough_batch_masses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dough_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained();
            $table->decimal('planned_quantity', 12, 3)->default(0);
            $table->decimal('leftover_quantity', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['dough_batch_id', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dough_batch_masses');
    }
};

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
        Schema::create('recipe_subrecipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('child_recipe_id')->constrained('recipes')->restrictOnDelete();
            $table->decimal('quantity_used', 10, 3);
            $table->string('unit');
            $table->decimal('cost_calculated', 10, 4)->nullable();
            $table->timestamps();
            $table->index('recipe_id');
            $table->index('child_recipe_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_subrecipe_lines');
    }
};

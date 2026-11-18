<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una fila de la grilla: un artículo y la cantidad de cada día
     * (day_1 = lunes … day_7 = domingo, numeración ISO).
     */
    public function up(): void
    {
        Schema::create('order_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_sheet_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            foreach (range(1, 7) as $day) {
                $table->decimal("day_{$day}", 12, 3)->default(0);
            }
            $table->timestamps();

            $table->unique(['order_sheet_week_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sheet_lines');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una fila de la semana de un cliente: un artículo y la cantidad de cada
     * día (day_1 = lunes … day_7 = domingo). `container_type` dice en qué
     * medida se tipeó (null = unidades, bandeja/lata = recipientes).
     */
    public function up(): void
    {
        Schema::create('customer_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('container_type', 20)->nullable();
            foreach (range(1, 7) as $day) {
                $table->decimal("day_{$day}", 12, 3)->default(0);
            }
            $table->timestamps();

            $table->unique(['customer_order_week_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_lines');
    }
};

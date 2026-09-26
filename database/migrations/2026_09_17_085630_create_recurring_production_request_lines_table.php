<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espejo exacto de production_order_lines: artículo + cantidad que trae
     * cada instancia nueva del recurrente. Sin tenant_id propio, igual que
     * su espejo — tenant-safe por el padre.
     */
    public function up(): void
    {
        Schema::create('recurring_production_request_lines', function (Blueprint $table) {
            $table->id();
            // Nombre de constraint explícito y corto: el que autogenera
            // Laravel para esta columna larga excede los 64 caracteres que
            // permite MySQL (error 1059).
            $table->foreignId('recurring_production_request_id')
                ->constrained(indexName: 'recurring_production_request_lines_request_id_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 10);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_production_request_lines');
    }
};

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20); // App\Enums\ProductType: 'manufactured' | 'resale'
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete(); // solo manufactured
            $table->string('unit', 10);
            $table->decimal('cost_per_unit', 14, 4)->nullable(); // resale: lo mantiene Compras; manufactured: null (se deriva de la receta)
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
            $table->index(['tenant_id', 'type']);
            $table->unique(['tenant_id', 'barcode']); // el escaneo resuelve un producto sin ambigüedad
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

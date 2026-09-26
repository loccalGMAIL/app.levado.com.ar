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
        Schema::table('ingredients', function (Blueprint $table) {
            // Trazabilidad ("¿en qué se convirtió este insumo?") e idempotencia
            // (bloquea convertirlo dos veces). Ver IngredientToProductConverter.
            $table->foreignId('converted_to_product_id')->nullable()->after('active')
                ->constrained('products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_to_product_id');
        });
    }
};

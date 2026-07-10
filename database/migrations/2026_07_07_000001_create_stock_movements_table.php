<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger inmutable de movimientos de stock: solo INSERT, nunca UPDATE ni DELETE.
     * Toda corrección se registra como contramovimiento (reverses_movement_id).
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('stockable_type', 20); // 'ingredient' | 'packaging'
            $table->unsignedBigInteger('stockable_id');
            $table->string('type', 20); // App\Enums\StockMovementType
            $table->decimal('quantity', 14, 4); // con signo, en la unidad del ítem
            $table->decimal('unit_cost', 14, 4)->default(0); // snapshot al momento del movimiento
            $table->string('reason')->nullable();
            $table->string('reference_type', 30)->nullable(); // 'purchase_line'
            $table->unsignedBigInteger('reference_id')->nullable(); // sin FK: la línea puede borrarse, el ledger queda
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'stockable_type', 'stockable_id', 'location_id'], 'stock_movements_stockable_idx');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

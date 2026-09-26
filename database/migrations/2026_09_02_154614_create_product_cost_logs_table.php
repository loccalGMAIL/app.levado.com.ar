<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_cost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nullable: los costos tipeados a mano no vienen de ninguna factura.
            $table->foreignId('purchase_line_id')->nullable()->constrained()->nullOnDelete();
            // 14,4 espeja products.cost_per_unit (las tablas hermanas son 10,4
            // porque espejan ingredients.cost_per_unit).
            $table->decimal('cost_per_unit', 14, 4);
            $table->string('source', 20); // CostLogSource: compra | manual
            $table->timestamp('recorded_at');

            $table->index(['product_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_cost_logs');
    }
};

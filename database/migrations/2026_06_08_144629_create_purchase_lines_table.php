<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('purchaseable_type', 20); // 'ingredient' | 'packaging'
            $table->unsignedBigInteger('purchaseable_id');
            $table->decimal('quantity_purchased', 10, 4);
            $table->string('purchase_unit', 10); // Unit enum value
            $table->decimal('unit_price', 10, 4);
            $table->decimal('subtotal', 10, 4);
            $table->timestamps();

            $table->index('purchase_id');
            $table->index(['purchaseable_type', 'purchaseable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
    }
};

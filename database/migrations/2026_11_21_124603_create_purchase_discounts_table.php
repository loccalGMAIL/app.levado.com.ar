<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_line_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('kind', 20);
            $table->decimal('rate', 7, 4)->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('iva_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_discounts');
    }
};

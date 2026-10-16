<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_perceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('perception_concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('jurisdiction', 30)->nullable();
            $table->string('description');
            $table->decimal('base_amount', 14, 2)->nullable();
            $table->decimal('rate', 7, 4)->nullable();
            $table->decimal('amount', 14, 2);
            $table->boolean('amount_is_manual')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_perceptions');
    }
};

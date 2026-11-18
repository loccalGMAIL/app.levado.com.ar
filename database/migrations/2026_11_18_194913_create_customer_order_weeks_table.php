<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una semana (lunes a domingo) de pedidos de un cliente. `starts_on` es
     * siempre el lunes.
     */
    public function up(): void
    {
        Schema::create('customer_order_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->timestamps();

            $table->unique(['customer_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_weeks');
    }
};

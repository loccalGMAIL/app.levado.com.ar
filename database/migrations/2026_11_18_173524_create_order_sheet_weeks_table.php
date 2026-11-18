<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una semana (de lunes a domingo) de una planilla. Se crea al abrirla
     * copiando la última semana cargada, y desde ahí se edita sola.
     */
    public function up(): void
    {
        Schema::create('order_sheet_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_sheet_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->timestamps();

            $table->unique(['order_sheet_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sheet_weeks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            // Null = renglón libre (reconocimiento económico sin mercadería de por
            // medio, como una rotura). Con valor, ata la devolución a lo que ese
            // renglón de compra hizo entrar al stock.
            $table->foreignId('purchase_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->string('unit', 10);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('iva_rate', 5, 4)->default(0.21);
            $table->decimal('subtotal', 14, 4);
            // Si descuenta stock. Sólo tiene efecto cuando hay purchase_line_id:
            // sin renglón de origen no hay de dónde sacar la entrada a revertir.
            $table->boolean('affects_stock')->default(true);
            $table->timestamp('stock_applied_at')->nullable();
            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('purchase_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
    }
};

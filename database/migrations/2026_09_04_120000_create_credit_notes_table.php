<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            // Nullable: la NC nace atada a una compra, pero si esa compra se
            // borra después la nota sigue existiendo como documento propio.
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note_number', 50)->nullable();
            $table->date('note_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'note_date']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->unique(
                ['tenant_id', 'supplier_id', 'note_number'],
                'credit_notes_tenant_supplier_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};

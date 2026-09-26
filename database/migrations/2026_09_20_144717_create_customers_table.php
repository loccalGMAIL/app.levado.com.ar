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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            // Repartidor a cargo de este cliente (nullable): sin uso todavía más
            // allá de informativo, gancho para cuando un repartidor con login
            // filtre "mis clientes" (ver delivery_people.user_id).
            $table->foreignId('delivery_person_id')->nullable()->constrained()->nullOnDelete();
            // Fiscales: sin uso hasta que exista facturación/cuenta corriente,
            // se agregan ahora para no migrar datos sobre filas ya creadas.
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('condicion_iva', 5)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'delivery_person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

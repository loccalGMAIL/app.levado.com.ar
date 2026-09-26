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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // El obrador donde se produce. Hoy siempre la sucursal default del
            // negocio (ver decision-multi-sucursal): las operaciones de
            // Producción todavía no tienen selector de sucursal.
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // NULL en las plantillas (is_template = true): una plantilla no
            // tiene fecha, se instancia con una al repetirla.
            $table->date('scheduled_for')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_template')->default(false);
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('produced_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scheduled_for']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_template']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};

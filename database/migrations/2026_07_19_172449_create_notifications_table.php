<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feed de alertas del negocio (centro de notificaciones, tenant-scoped).
     * Único punto de escritura: NotificationService.
     *
     * dedupe_key hace idempotentes las alertas de estado (low stock, costo
     * desactualizado, compras sin imputar): mientras siga sin resolver, no se
     * crea otra con la misma clave. Las de evento (salto de costo) usan una
     * clave por línea de compra para no duplicar al re-imputar.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);       // NotificationType
            $table->string('severity', 10);   // red | amber | blue
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'resolved_at', 'read_at'], 'notifications_feed_index');
            $table->index(['tenant_id', 'dedupe_key'], 'notifications_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

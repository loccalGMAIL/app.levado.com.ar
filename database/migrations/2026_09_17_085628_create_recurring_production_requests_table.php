<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El molde de un pedido recurrente ("todos los lunes a sábado a la
     * Cafetería"): destino + días de semana en que se repite. Las
     * instancias reales son ProductionOrderRequest normales (editables una
     * por una, sin afectar al resto) vinculadas acá vía
     * production_order_requests.recurring_production_request_id.
     */
    public function up(): void
    {
        Schema::create('recurring_production_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Mismo par morph que production_order_requests.destination_type/id
            // (DeliveryDestinationType, ya en el morph map global).
            $table->string('destination_type', 30);
            $table->unsignedBigInteger('destination_id');
            // Días ISO-8601 (1=lunes...7=domingo, Carbon::dayOfWeekIso) —
            // NO dayOfWeek (0=domingo): el off-by-one sólo se nota los domingos.
            $table->json('weekdays');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'active']);
            $table->index(['tenant_id', 'destination_type', 'destination_id'], 'recurring_production_requests_destination_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_production_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierre de cámara: el hornero cuenta lo que quedó en cámara y la
     * diferencia con lo esperado (menos la merma de horno) es lo horneado.
     * Lo esperado se congela al abrir el cierre.
     */
    public function up(): void
    {
        Schema::create('chamber_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('chamber_location_id')->constrained('locations');
            $table->string('status', 20)->default('draft');
            $table->timestamp('opened_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamber_closings');
    }
};

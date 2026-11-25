<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un amasado: lo que realmente se amasó un día, con renglones mixtos (a
     * cámara y directo). Al confirmarse consume los insumos y entra el crudo
     * a cámara o el terminado a la sucursal. Solo el borrador se borra.
     */
    public function up(): void
    {
        Schema::create('dough_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('kitchen_location_id')->constrained('locations');
            $table->date('dough_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'dough_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dough_batches');
    }
};

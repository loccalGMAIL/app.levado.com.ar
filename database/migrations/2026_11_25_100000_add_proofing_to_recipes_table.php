<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si lo que rinde la receta pasa por cámara de leudado antes del horno
     * (se amasa el día anterior) o va directo. Las horas máximas en cámara se
     * guardan para cuando el stock de cámara tenga lotes; hoy no se usan.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('uses_proofing')->default(false)->after('units_per_container');
            $table->unsignedSmallInteger('max_proofing_hours')->nullable()->after('uses_proofing');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['uses_proofing', 'max_proofing_hours']);
        });
    }
};

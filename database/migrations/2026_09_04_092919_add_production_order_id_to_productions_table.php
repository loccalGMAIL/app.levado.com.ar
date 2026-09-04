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
        Schema::table('productions', function (Blueprint $table) {
            // NULL en las producciones ad-hoc de "producir ahora" (pantalla sin
            // cambios). Las que nacen de una orden quedan atadas acá.
            $table->foreignId('production_order_id')->nullable()->after('recipe_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_order_id');
        });
    }
};

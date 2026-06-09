<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            // Texto del renglón tal como se leyó de la factura.
            $table->string('raw_name')->nullable()->after('purchase_id');

            // Una línea capturada puede estar todavía sin matchear con el catálogo.
            $table->string('purchaseable_type', 20)->nullable()->change();
            $table->unsignedBigInteger('purchaseable_id')->nullable()->change();

            // Marca cuándo se imputó el costo de esta línea al insumo/descartable.
            $table->timestamp('cost_applied_at')->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn(['raw_name', 'cost_applied_at']);
            $table->string('purchaseable_type', 20)->nullable(false)->change();
            $table->unsignedBigInteger('purchaseable_id')->nullable(false)->change();
        });
    }
};

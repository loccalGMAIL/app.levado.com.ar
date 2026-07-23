<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Política de precio de la celda (artículo × lista).
            $table->string('policy_type', 20)->default('manual')->after('price');
            $table->decimal('policy_value', 6, 2)->nullable()->after('policy_type'); // % para margen/recargo
            // Con política, el precio computado puede no existir aún (costo nulo).
            $table->decimal('price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn(['policy_type', 'policy_value']);
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });
    }
};

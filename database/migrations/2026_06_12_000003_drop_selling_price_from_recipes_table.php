<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * recipe_prices pasa a ser la única fuente de verdad del precio de venta.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }

    /**
     * Restaura la columna y la repuebla desde la lista de precios default.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 2)->nullable()->after('yield_unit');
        });

        $rows = DB::table('recipe_prices')
            ->join('price_lists', 'price_lists.id', '=', 'recipe_prices.price_list_id')
            ->where('price_lists.is_default', true)
            ->get(['recipe_prices.recipe_id', 'recipe_prices.price']);

        foreach ($rows as $row) {
            DB::table('recipes')->where('id', $row->recipe_id)->update(['selling_price' => $row->price]);
        }
    }
};

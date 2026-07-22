<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia los precios de venta de cada receta (recipe_prices) al producto elaborado
 * vinculado (product_prices), como parte de mover el pricing de la Receta al Artículo.
 * Idempotente: no pisa un product_price ya existente. recipe_prices queda intacta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('recipe_prices')
            ->join('products', function ($join) {
                $join->on('products.recipe_id', '=', 'recipe_prices.recipe_id')
                    ->where('products.type', '=', 'manufactured');
            })
            ->select(
                'recipe_prices.tenant_id',
                'recipe_prices.price_list_id',
                'products.id as product_id',
                'recipe_prices.price',
            )
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('product_prices')
                ->where('price_list_id', $row->price_list_id)
                ->where('product_id', $row->product_id)
                ->exists();

            if (! $exists) {
                DB::table('product_prices')->insert([
                    'tenant_id' => $row->tenant_id,
                    'price_list_id' => $row->price_list_id,
                    'product_id' => $row->product_id,
                    'price' => $row->price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Backfill de datos: no se revierte (recipe_prices sigue siendo la fuente original).
    }
};

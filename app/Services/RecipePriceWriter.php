<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;

class RecipePriceWriter
{
    /**
     * Setea el precio de una receta en una lista. Null elimina el precio.
     * Loguea en recipe_price_logs solo cuando el monto es nuevo o cambió.
     */
    public function set(Recipe $recipe, PriceList $priceList, ?float $price): ?RecipePrice
    {
        $existing = RecipePrice::where('price_list_id', $priceList->id)
            ->where('recipe_id', $recipe->id)
            ->first();

        if ($price === null) {
            $existing?->delete();

            return null;
        }

        $priceChanged = $existing === null || (float) $existing->price !== $price;

        $recipePrice = RecipePrice::updateOrCreate(
            ['price_list_id' => $priceList->id, 'recipe_id' => $recipe->id],
            ['tenant_id' => $recipe->tenant_id, 'price' => $price],
        );

        if ($priceChanged) {
            $recipe->priceLogs()->create([
                'price_list_id' => $priceList->id,
                'price' => $price,
                'recorded_at' => now(),
            ]);
        }

        return $recipePrice;
    }
}

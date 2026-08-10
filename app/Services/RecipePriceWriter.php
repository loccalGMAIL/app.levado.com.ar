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
        $recipePrice = RecipePrice::firstOrNew([
            'price_list_id' => $priceList->id,
            'recipe_id' => $recipe->id,
        ]);

        if ($price === null) {
            if ($recipePrice->exists) {
                $recipePrice->delete();
            }

            return null;
        }

        $recipePrice->fill(['tenant_id' => $recipe->tenant_id, 'price' => $price]);
        $priceChanged = ! $recipePrice->exists || $recipePrice->isDirty('price');

        $recipePrice->save();

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

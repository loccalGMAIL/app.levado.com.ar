<?php

namespace Database\Factories;

use App\Models\RecipePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipePrice>
 */
class RecipePriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'price' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}

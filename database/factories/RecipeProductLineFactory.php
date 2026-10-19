<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeProductLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeProductLine>
 */
class RecipeProductLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'product_id' => Product::factory()->resale(),
            'quantity' => 1,
            'unit' => Unit::Unidad->value,
        ];
    }
}

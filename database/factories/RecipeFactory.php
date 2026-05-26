<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'yield_quantity' => fake()->randomFloat(0, 6, 60),
            'yield_unit' => Unit::Unidad->value,
            'active' => true,
            'is_semi_elaborate' => false,
            'unit_cost' => null,
        ];
    }

    public function semiElaborate(): static
    {
        return $this->state(['is_semi_elaborate' => true]);
    }
}

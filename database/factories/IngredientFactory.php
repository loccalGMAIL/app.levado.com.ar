<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'unit' => fake()->randomElement(Unit::cases())->value,
            'cost_per_unit' => fake()->randomFloat(4, 0.01, 500),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

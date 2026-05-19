<?php

namespace Database\Factories;

use App\Models\FixedCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedCost>
 */
class FixedCostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'monthly_amount' => fake()->randomFloat(2, 500, 50000),
            'active' => true,
        ];
    }
}

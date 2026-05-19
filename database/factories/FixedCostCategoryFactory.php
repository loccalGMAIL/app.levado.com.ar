<?php

namespace Database\Factories;

use App\Models\FixedCostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedCostCategory>
 */
class FixedCostCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}

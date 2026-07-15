<?php

namespace Database\Factories;

use App\Models\VariableExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariableExpenseCategory>
 */
class VariableExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
        ];
    }
}

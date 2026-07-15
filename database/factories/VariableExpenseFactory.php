<?php

namespace Database\Factories;

use App\Models\VariableExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariableExpense>
 */
class VariableExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'expense_date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Mostrador', 'Mayorista', 'Cafeterías', 'Reventa', 'Eventos']),
            'adjustment_pct' => null,
            'is_default' => false,
            'active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['name' => 'General', 'is_default' => true, 'adjustment_pct' => null]);
    }
}

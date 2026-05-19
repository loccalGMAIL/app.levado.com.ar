<?php

namespace Database\Factories;

use App\Models\LaborType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaborType>
 */
class LaborTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Panadero', 'Pastelero', 'Ayudante', 'Maestro panadero', 'Aprendiz']),
            'hourly_rate' => fake()->randomFloat(2, 500, 5000),
            'active' => true,
        ];
    }
}

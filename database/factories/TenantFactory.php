<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'country' => 'AR',
            'currency' => 'ARS',
            'productive_hours_month' => 160,
            'active' => true,
            'onboarding_completed_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

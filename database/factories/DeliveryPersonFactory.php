<?php

namespace Database\Factories;

use App\Models\DeliveryPerson;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryPerson>
 */
class DeliveryPersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'notes' => null,
            'active' => true,
            'user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

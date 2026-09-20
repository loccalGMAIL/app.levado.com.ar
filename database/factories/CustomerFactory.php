<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => null,
            'address' => fake()->streetAddress(),
            'city' => null,
            'notes' => null,
            'active' => true,
            'delivery_person_id' => null,
            'legal_name' => null,
            'tax_id' => null,
            'condicion_iva' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerOrderWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOrderWeek>
 */
class CustomerOrderWeekFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            // El tenant sale del cliente, para que ambos coincidan siempre.
            'tenant_id' => fn (array $attributes) => Customer::withoutGlobalScopes()->findOrFail($attributes['customer_id'])->tenant_id,
            'starts_on' => CarbonImmutable::now()->startOfWeek()->toDateString(),
        ];
    }

    public function startingOn(string $monday): static
    {
        return $this->state(['starts_on' => $monday]);
    }
}

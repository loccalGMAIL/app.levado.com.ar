<?php

namespace Database\Factories;

use App\Enums\DeliveryDestinationType;
use App\Models\Location;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringProductionRequest>
 */
class RecurringProductionRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'destination_type' => DeliveryDestinationType::Location->value,
            'destination_id' => fn (array $attributes) => Location::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            // Lunes a sábado — el caso real que motivó la feature.
            'weekdays' => [1, 2, 3, 4, 5, 6],
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    public function everyday(): static
    {
        return $this->state(['weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
    }
}

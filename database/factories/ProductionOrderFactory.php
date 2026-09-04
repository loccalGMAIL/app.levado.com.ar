<?php

namespace Database\Factories;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOrderType;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => fn (array $attributes) => Tenant::find($attributes['tenant_id'])->defaultLocation()->id,
            'type' => ProductionOrderType::Daily->value,
            'scheduled_for' => now()->toDateString(),
            'status' => ProductionOrderStatus::Draft->value,
            'is_template' => false,
            'name' => null,
            'notes' => null,
        ];
    }

    public function spontaneous(): static
    {
        return $this->state(['type' => ProductionOrderType::Spontaneous->value]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => ProductionOrderStatus::Confirmed->value, 'confirmed_at' => now()]);
    }

    public function done(): static
    {
        return $this->state(['status' => ProductionOrderStatus::Done->value, 'produced_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ProductionOrderStatus::Cancelled->value, 'cancelled_at' => now()]);
    }

    public function template(): static
    {
        return $this->state(['is_template' => true, 'scheduled_for' => null, 'name' => fake()->words(2, true)]);
    }
}

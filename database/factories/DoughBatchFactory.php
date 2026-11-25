<?php

namespace Database\Factories;

use App\Enums\ProductionRecordStatus;
use App\Models\DoughBatch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoughBatch>
 */
class DoughBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => fn (array $attributes) => (int) DoughBatch::withoutGlobalScopes()->withTrashed()->where('tenant_id', $attributes['tenant_id'])->max('number') + 1,
            // La cocina es la sucursal default del mismo negocio.
            'kitchen_location_id' => fn (array $attributes) => Tenant::query()->findOrFail($attributes['tenant_id'])->defaultLocation()->id,
            'dough_date' => now()->toDateString(),
            'status' => ProductionRecordStatus::Draft->value,
        ];
    }

    public function on(string $date): static
    {
        return $this->state(['dough_date' => $date]);
    }
}

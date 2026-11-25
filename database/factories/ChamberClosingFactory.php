<?php

namespace Database\Factories;

use App\Enums\ProductionRecordStatus;
use App\Models\ChamberClosing;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChamberClosing>
 */
class ChamberClosingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => fn (array $attributes) => (int) ChamberClosing::withoutGlobalScopes()->where('tenant_id', $attributes['tenant_id'])->max('number') + 1,
            'chamber_location_id' => fn (array $attributes) => Tenant::query()->findOrFail($attributes['tenant_id'])->proofingChamber()->id,
            'status' => ProductionRecordStatus::Draft->value,
            'opened_at' => now(),
        ];
    }
}

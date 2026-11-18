<?php

namespace Database\Factories;

use App\Models\OrderSheet;
use App\Models\OrderSheetWeek;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderSheetWeek>
 */
class OrderSheetWeekFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_sheet_id' => OrderSheet::factory(),
            // El tenant sale de la planilla, para que ambos coincidan siempre.
            'tenant_id' => fn (array $attributes) => OrderSheet::withoutGlobalScopes()->findOrFail($attributes['order_sheet_id'])->tenant_id,
            'starts_on' => CarbonImmutable::now()->startOfWeek()->toDateString(),
        ];
    }

    public function startingOn(string $monday): static
    {
        return $this->state(['starts_on' => $monday]);
    }
}

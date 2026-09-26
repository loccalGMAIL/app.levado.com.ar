<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Product;
use App\Models\RecurringProductionRequest;
use App\Models\RecurringProductionRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringProductionRequestLine>
 */
class RecurringProductionRequestLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recurring_production_request_id' => RecurringProductionRequest::factory(),
            'product_id' => fn (array $attributes) => Product::factory()->manufactured()->create([
                'tenant_id' => RecurringProductionRequest::find($attributes['recurring_production_request_id'])?->tenant_id,
            ])->id,
            'quantity' => fake()->randomFloat(2, 1, 50),
            'unit' => Unit::Unidad->value,
            'position' => 1,
        ];
    }
}

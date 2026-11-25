<?php

namespace Database\Factories;

use App\Models\DoughBatch;
use App\Models\DoughBatchLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoughBatchLine>
 */
class DoughBatchLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dough_batch_id' => DoughBatch::factory(),
            // El artículo es del mismo negocio que el amasado.
            'product_id' => fn (array $attributes) => Product::factory()->manufactured()->create([
                'tenant_id' => DoughBatch::withoutGlobalScopes()->findOrFail($attributes['dough_batch_id'])->tenant_id,
            ])->id,
            'position' => 0,
            'goes_to_chamber' => false,
            'planned_units' => 0,
            'actual_units' => 0,
            'waste_units' => 0,
        ];
    }
}

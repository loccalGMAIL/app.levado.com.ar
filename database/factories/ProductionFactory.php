<?php

namespace Database\Factories;

use App\Enums\ProductionStatus;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Production;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // La sucursal y el producto heredan el tenant de la producción.
            'location_id' => fn (array $attributes) => Tenant::find($attributes['tenant_id'])->defaultLocation()->id,
            'product_id' => fn (array $attributes) => Product::factory()->manufactured()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'recipe_id' => null,
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit' => Unit::Unidad->value,
            'unit_cost' => 0,
            'total_cost' => 0,
            'status' => ProductionStatus::Confirmed->value,
            'notes' => null,
            'produced_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => ProductionStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }
}

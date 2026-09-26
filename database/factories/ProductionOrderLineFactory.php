<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrderLine>
 */
class ProductionOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_request_id' => ProductionOrderRequest::factory(),
            // El producto hereda el tenant del pedido vía su orden.
            'product_id' => fn (array $attributes) => Product::factory()->manufactured()->create([
                'tenant_id' => ProductionOrderRequest::find($attributes['production_order_request_id'])
                    ->tenant_id ?? Tenant::factory()->create()->id,
            ])->id,
            'quantity' => fake()->randomFloat(2, 1, 50),
            'unit' => Unit::Unidad->value,
            'position' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\CostLogSource;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCostLog>
 */
class ProductCostLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'purchase_line_id' => null,
            'cost_per_unit' => fake()->randomFloat(4, 0.01, 500),
            'source' => CostLogSource::Manual->value,
            'recorded_at' => now(),
        ];
    }

    public function fromPurchase(): static
    {
        return $this->state(['source' => CostLogSource::Purchase->value]);
    }
}

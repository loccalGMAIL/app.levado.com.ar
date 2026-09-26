<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'type' => ProductType::Resale->value,
            'recipe_id' => null,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => fake()->randomFloat(4, 0.01, 500),
            'sku' => null,
            'barcode' => null,
            'active' => true,
        ];
    }

    public function manufactured(): static
    {
        // La receta hereda el tenant del producto para no cruzar tenants en la factory.
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Manufactured->value,
            'recipe_id' => Recipe::factory()->create([
                'tenant_id' => $attributes['tenant_id'] ?? Tenant::factory()->create()->id,
            ])->id,
            'cost_per_unit' => null,
        ]);
    }

    public function resale(): static
    {
        return $this->state(['type' => ProductType::Resale->value]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}

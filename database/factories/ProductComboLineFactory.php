<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductComboLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductComboLine>
 */
class ProductComboLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'combo_id' => Product::factory()->combo(),
            'product_id' => Product::factory(),
            'quantity' => 1,
        ];
    }
}

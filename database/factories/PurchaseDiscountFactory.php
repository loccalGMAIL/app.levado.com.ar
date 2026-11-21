<?php

namespace Database\Factories;

use App\Enums\DiscountKind;
use App\Models\PurchaseDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseDiscount>
 */
class PurchaseDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_line_id' => null,
            'description' => 'Descuento',
            'kind' => DiscountKind::Amount->value,
            'rate' => null,
            'amount' => 100,
            'iva_amount' => 0,
        ];
    }
}

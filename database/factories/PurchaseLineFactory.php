<?php

namespace Database\Factories;

use App\Enums\CatalogItemType;
use App\Models\PurchaseLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseLine>
 */
class PurchaseLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Sin asociación por defecto (renglón recién digitalizado, pendiente de
     * match). Usar matchedToIngredient()/matchedToPackaging() para asociarlo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 50);
        $unitPrice = fake()->randomFloat(2, 100, 20000);

        return [
            'raw_name' => mb_strtoupper(fake()->words(3, true)),
            'purchaseable_type' => null,
            'purchaseable_id' => null,
            'quantity_purchased' => $quantity,
            'purchase_unit' => 'u',
            'unit_price' => $unitPrice,
            'iva_rate' => 0.21,
            'percepcion_rate' => null,
            'subtotal' => round($quantity * $unitPrice, 2),
            'cost_applied_at' => null,
        ];
    }

    public function matchedToIngredient(int $ingredientId): static
    {
        return $this->state(fn () => [
            'purchaseable_type' => CatalogItemType::Ingredient->value,
            'purchaseable_id' => $ingredientId,
        ]);
    }

    public function matchedToPackaging(int $packagingId): static
    {
        return $this->state(fn () => [
            'purchaseable_type' => CatalogItemType::Packaging->value,
            'purchaseable_id' => $packagingId,
        ]);
    }
}

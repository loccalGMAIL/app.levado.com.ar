<?php

namespace Database\Factories;

use App\Models\CreditNoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteLine>
 */
class CreditNoteLineFactory extends Factory
{
    /**
     * Renglón libre por defecto (reconocimiento económico sin mercadería).
     * Usar linkedToPurchaseLine() para atarlo a una entrada de compra.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 100, 20000);

        return [
            'purchase_line_id' => null,
            'description' => mb_strtoupper(fake()->words(3, true)),
            'quantity' => $quantity,
            'unit' => 'u',
            'unit_price' => $unitPrice,
            'iva_rate' => 0.21,
            'subtotal' => round($quantity * $unitPrice, 2),
            'affects_stock' => false,
            'stock_applied_at' => null,
        ];
    }

    public function linkedToPurchaseLine(int $purchaseLineId): static
    {
        return $this->state(fn () => [
            'purchase_line_id' => $purchaseLineId,
            'description' => null,
            'affects_stock' => true,
        ]);
    }
}

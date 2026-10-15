<?php

namespace Database\Factories;

use App\Enums\PerceptionType;
use App\Models\PurchasePerception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasePerception>
 */
class PurchasePerceptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PerceptionType::Otro->value,
            'jurisdiction' => null,
            'description' => 'Percepción',
            'base_amount' => null,
            'rate' => 3,
            'amount' => 0,
            'amount_is_manual' => false,
        ];
    }
}

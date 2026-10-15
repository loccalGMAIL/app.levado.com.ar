<?php

namespace Database\Factories;

use App\Enums\PerceptionType;
use App\Models\PerceptionConcept;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerceptionConcept>
 */
class PerceptionConceptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PerceptionType::Iva->value,
            'jurisdiction' => null,
            'name' => 'Perc. IVA',
            'default_rate' => 3,
            'is_active' => true,
        ];
    }
}

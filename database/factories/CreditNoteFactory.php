<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    /**
     * El proveedor hereda el tenant de la nota (el que llega vía ->for($tenant)),
     * para que la factory nunca produzca una nota con proveedor de otro tenant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create([
                'tenant_id' => $attributes['tenant_id'] ?? Tenant::factory()->create()->id,
            ])->id,
            'purchase_id' => null,
            'note_number' => fake()->unique()->numerify('NC-0001-########'),
            'note_date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'notes' => null,
        ];
    }
}

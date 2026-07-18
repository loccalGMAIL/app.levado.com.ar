<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * El proveedor hereda el tenant de la compra (el que llega vía ->for($tenant)),
     * para que la factory nunca produzca una compra con proveedor de otro tenant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create([
                'tenant_id' => $attributes['tenant_id'] ?? Tenant::factory()->create()->id,
            ])->id,
            'invoice_number' => fake()->unique()->numerify('0001-########'),
            'invoice_date' => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
            'invoice_total' => null,
            'notes' => null,
        ];
    }
}

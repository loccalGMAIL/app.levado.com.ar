<?php

namespace Database\Factories;

use App\Enums\DeliveryDestinationType;
use App\Models\DeliveryPerson;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrderRequest>
 */
class ProductionOrderRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // La orden y el destino heredan el tenant del pedido.
            'production_order_id' => fn (array $attributes) => ProductionOrder::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'destination_type' => DeliveryDestinationType::Location->value,
            'destination_id' => fn (array $attributes) => Location::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
            ])->id,
            'notes' => null,
            // MAX+1 entre los pedidos ya existentes de la orden — position 0
            // fijo colisionaría con el unique (production_order_id, position)
            // apenas una orden tenga 2 pedidos.
            'position' => fn (array $attributes) => (int) ProductionOrderRequest::where('production_order_id', $attributes['production_order_id'])->max('position') + 1,
        ];
    }

    /** Destino repartidor en vez de sucursal (el default). */
    public function toDeliveryPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'destination_type' => DeliveryDestinationType::DeliveryPerson->value,
            'destination_id' => DeliveryPerson::factory()->create([
                'tenant_id' => $attributes['tenant_id'] ?? Tenant::factory()->create()->id,
            ])->id,
        ]);
    }
}

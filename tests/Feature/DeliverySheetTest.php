<?php

use App\Models\DeliveryPerson;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;

// productionSetup() es un helper global (ProductionControllerTest).

test('la planilla de reparto agrupa los artículos por destino', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $sucursal = Location::factory()->for($tenant)->create(['name' => 'Sucursal Centro']);
    $requestA = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'location',
        'destination_id' => $sucursal->id,
    ]);
    $requestA->lines()->create(['product_id' => $product->id, 'quantity' => 12, 'unit' => $product->unit->value]);

    $repartidor = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Juan Reparto']);
    $requestB = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'delivery_person',
        'destination_id' => $repartidor->id,
    ]);
    $requestB->lines()->create(['product_id' => $product->id, 'quantity' => 7, 'unit' => $product->unit->value]);

    $response = $this->actingAs($user)->get(route('production-orders.delivery-sheet', $order));

    $response->assertOk()
        ->assertSeeInOrder(['Sucursal Centro', '12,00'])
        ->assertSeeInOrder(['Juan Reparto', '7,00']);
});

test('la planilla de una orden sin pedidos muestra el estado vacío', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', $order))
        ->assertOk()
        ->assertSee('Esta orden no tiene pedidos.');
});

test('aislamiento: no se puede ver la planilla de una orden de otro tenant', function () {
    [$user] = productionSetup();

    $otherTenant = Tenant::factory()->create();
    $otherOrder = ProductionOrder::factory()->for($otherTenant)->create(['location_id' => $otherTenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', $otherOrder))
        ->assertNotFound();
});

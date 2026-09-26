<?php

use App\Enums\TenantUserRole;
use App\Models\Customer;
use App\Models\DeliveryPerson;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;

// productionSetup() es un helper global (ProductionControllerTest).

test('la planilla de producción muestra los productos agregados y los insumos necesarios', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $request = ProductionOrderRequest::factory()->for($tenant)->toCustomer()->create([
        'production_order_id' => $order->id,
    ]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 24, 'unit' => $product->unit->value]);

    $this->actingAs($user)
        ->get(route('production-orders.production-sheet', $order))
        ->assertOk()
        ->assertSeeInOrder(['Productos a producir', $product->name, '24,00'])
        ->assertSeeInOrder(['Insumos necesarios', $harina->name]);
});

test('la planilla de reparto agrupa por repartidor y por sucursal', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $juan = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Juan']);
    $clienteDeJuan = Customer::factory()->for($tenant)->create(['name' => 'Kiosco La Esquina', 'delivery_person_id' => $juan->id]);
    $requestJuan = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $clienteDeJuan->id,
    ]);
    $requestJuan->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit' => $product->unit->value]);

    $sinRepartidor = Customer::factory()->for($tenant)->create(['name' => 'Almacén Suelto']);
    $requestSinRepartidor = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $sinRepartidor->id,
    ]);
    $requestSinRepartidor->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit' => $product->unit->value]);

    $sucursal = Location::factory()->for($tenant)->create(['name' => 'Sucursal Centro']);
    $requestSucursal = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'location',
        'destination_id' => $sucursal->id,
    ]);
    $requestSucursal->lines()->create(['product_id' => $product->id, 'quantity' => 8, 'unit' => $product->unit->value]);

    $response = $this->actingAs($user)->get(route('production-orders.delivery-sheet', $order));

    $response->assertOk()
        ->assertSeeInOrder(['Juan', 'Kiosco La Esquina', '10,00'])
        ->assertSee($tenant->defaultLocation()->name)
        ->assertSee('Almacén Suelto')
        ->assertSee('Retira en')
        ->assertSeeInOrder(['Sucursal Centro', '8,00']);
});

test('el total a llevar del repartidor suma las cantidades del mismo producto entre sus clientes', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $juan = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Juan']);

    $clienteA = Customer::factory()->for($tenant)->create(['name' => 'Kiosco La Esquina', 'delivery_person_id' => $juan->id]);
    $requestA = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $clienteA->id,
    ]);
    $requestA->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit' => $product->unit->value]);

    $clienteB = Customer::factory()->for($tenant)->create(['name' => 'Panadería Norte', 'delivery_person_id' => $juan->id]);
    $requestB = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $clienteB->id,
    ]);
    $requestB->lines()->create(['product_id' => $product->id, 'quantity' => 6, 'unit' => $product->unit->value]);

    $response = $this->actingAs($user)->get(route('production-orders.delivery-sheet', $order));

    $response->assertOk()
        // El total (16,00 = 10 + 6) tiene que aparecer una sola vez, en la
        // tabla de "Total a llevar" -no confundirse con las líneas 10,00/6,00
        // de cada pedido individual-.
        ->assertSeeInOrder(['Kiosco La Esquina', '10,00', 'Panadería Norte', '6,00', 'Total a llevar', '16,00']);
});

test('la planilla de reparto filtrada por grupo muestra sólo esos pedidos', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $juan = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Juan']);
    $clienteDeJuan = Customer::factory()->for($tenant)->create(['name' => 'Kiosco La Esquina', 'delivery_person_id' => $juan->id]);
    $requestJuan = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $clienteDeJuan->id,
    ]);
    $requestJuan->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit' => $product->unit->value]);

    $pedro = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Pedro']);
    $clienteDePedro = Customer::factory()->for($tenant)->create(['name' => 'Panadería Norte', 'delivery_person_id' => $pedro->id]);
    $requestPedro = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'customer',
        'destination_id' => $clienteDePedro->id,
    ]);
    $requestPedro->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', [$order, 'group' => 'driver-'.$juan->id]))
        ->assertOk()
        ->assertSee('Kiosco La Esquina')
        ->assertDontSee('Panadería Norte');
});

test('un grupo inexistente en la planilla de reparto devuelve 404', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', [$order, 'group' => 'driver-999']))
        ->assertNotFound();
});

test('los PDF de producción y reparto se descargan', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->toCustomer()->create([
        'production_order_id' => $order->id,
    ]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 6, 'unit' => $product->unit->value]);

    $this->actingAs($user)
        ->get(route('production-orders.production-sheet.pdf', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet.pdf', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('viewer puede ver las planillas de producción y reparto', function () {
    [$user, $tenant] = productionSetup(TenantUserRole::Viewer);
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)->get(route('production-orders.production-sheet', $order))->assertOk();
    $this->actingAs($user)->get(route('production-orders.delivery-sheet', $order))->assertOk();
});

test('aislamiento: no se puede ver la planilla de producción de otro tenant', function () {
    [$user] = productionSetup();

    $otherTenant = Tenant::factory()->create();
    $otherOrder = ProductionOrder::factory()->for($otherTenant)->create(['location_id' => $otherTenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.production-sheet', $otherOrder))
        ->assertNotFound();
});

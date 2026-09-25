<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\TenantUserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;

// tenantUserAs() (IngredientCrudTest) y productionSetup() (ProductionControllerTest) son helpers globales.

test('el índice de órdenes se renderiza', function () {
    [$user, $tenant] = productionSetup();
    ProductionOrder::factory()->for($tenant)->create();

    $this->actingAs($user)->get(route('production-orders.index'))->assertOk()->assertSee('Órdenes de producción');
});

test('el índice de órdenes no lista plantillas', function () {
    [$user, $tenant] = productionSetup();
    ProductionOrder::factory()->for($tenant)->template()->create(['name' => 'Plantilla del lunes']);

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertDontSee('Plantilla del lunes');
});

test('el índice ofrece "Nuevo pedido" como acción principal, ya no "Nueva orden"', function () {
    [$user, $tenant] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee('+ Nuevo pedido')
        ->assertDontSee('Nueva orden espontánea')
        ->assertDontSee('+ Nueva orden');
});

test('el índice ofrece planillas de producción, reparto y Confirmar como acciones rápidas por fila', function () {
    [$user, $tenant] = productionSetup();
    $draft = ProductionOrder::factory()->for($tenant)->create();
    $done = ProductionOrder::factory()->for($tenant)->done()->create();

    $response = $this->actingAs($user)->get(route('production-orders.index'))->assertOk();
    // No se busca el texto "Reparto": desde que el sidebar tiene un ítem con
    // ese mismo nombre, cualquier página lo matchea y el assert deja de
    // probar el link de la fila -se verifica la URL de la planilla en su lugar-.
    $response->assertSee(route('production-orders.production-sheet', $draft), false);
    $response->assertSee(route('production-orders.delivery-sheet', $draft), false);
    // "Confirmar" sólo tiene que aparecer para la orden en borrador, no para la ya terminada.
    $response->assertSeeInOrder(['Confirmar'], false);
    $response->assertSee(route('production-orders.transition', $draft), false);
    $response->assertDontSee(route('production-orders.transition', $done), false);
});

test('confirmar una orden desde el índice la avanza a Confirmada', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->from(route('production-orders.index'))
        ->patch(route('production-orders.transition', $order), ['status' => 'confirmed'])
        ->assertRedirect(route('production-orders.index'));

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Confirmed);
});

test('un viewer no ve el botón de cargar pedido', function () {
    [$user] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertDontSee('+ Nuevo pedido');
});

test('en el modal de "Nuevo pedido" destino, fecha y botones van en una fila, luego el picker de artículos', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'request_create_destination',
            'request_create_scheduled_for',
            'Cargar pedido',
            'x-ref="rows"',
            'request-create-picker',
        ], false);
});

test('en el modal de "Orden instantánea" los botones van arriba y el picker de artículos abajo', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'Producir ahora',
            'instant_create_destination',
            'x-ref="rows"',
            'instant-create-picker',
        ], false);
});

test('Enter en la cantidad de un renglón vuelve al picker en vez de enviar el formulario', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee('@keydown.enter.prevent="focusPicker()"', false);
});

test('el índice marca con 🔁 la orden que tiene un pedido recurrente', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $recurring = RecurringProductionRequest::factory()->for($tenant)->create([
        'destination_id' => $tenant->defaultLocation()->id,
    ]);
    ordersService()->addRequest($order, [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
        'recurring_production_request_id' => $recurring->id,
    ]);
    $plainOrder = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $response = $this->actingAs($user)->get(route('production-orders.index'))->assertOk();

    $response->assertSeeInOrder([str_pad((string) $order->number, 5, '0', STR_PAD_LEFT), '🔁']);
});

test('el índice muestra "Hoy" para una orden con fecha de hoy', function () {
    [$user, $tenant] = productionSetup();
    ProductionOrder::factory()->for($tenant)->create([
        'location_id' => $tenant->defaultLocation()->id,
        'scheduled_for' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee('Hoy');
});

test('owner puede crear una orden', function () {
    [$user, $tenant] = productionSetup();

    $response = $this->actingAs($user)->post(route('production-orders.store'), [
        'type' => 'daily',
        'scheduled_for' => now()->toDateString(),
    ]);

    $order = $tenant->productionOrders()->first();
    $response->assertRedirect(route('production-orders.show', $order));
    expect($order->status)->toBe(ProductionOrderStatus::Draft);
});

test('armar un pedido con un artículo por syncLines y verlo en el detalle', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)->post(route('production-orders.requests.store', $order), [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
    ])->assertRedirect();

    $request = $order->fresh()->productionOrderRequests()->first();

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 5]],
        ])
        ->assertOk();

    // Orden editable → la rama es la grilla Alpine (products/lines viajan como
    // JSON embebido, no como HTML formateado): se ancla contra la base, no
    // contra assertSee('5,00'). La rama de sólo lectura se ancla más abajo.
    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee($product->name);
    expect($request->lines()->where('product_id', $product->id)->first()->quantity)->toEqualWithDelta(5, 0.001);
});

test('el modal "Agregar pedido" del detalle carga destino y artículos en un solo paso', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSeeInOrder(['request_destination', 'Agregar pedido', 'x-ref="rows"'], false);

    $this->actingAs($user)->post(route('production-orders.requests.store', $order), [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
        'lines' => [['product_id' => $product->id, 'quantity' => 4]],
    ])->assertRedirect();

    // La orden sigue siendo una sola: el pedido se agregó a ÉSTA, no se
    // creó (ni se buscó) otra por fecha — a diferencia de
    // ProductionOrderService::placeRequest(), que sí hace ese find-or-create.
    expect($tenant->productionOrders()->count())->toBe(1);

    $request = $order->fresh()->productionOrderRequests()->first();
    expect($request->lines()->where('product_id', $product->id)->first()->quantity)->toEqualWithDelta(4, 0.001);
});

test('el detalle de una orden terminada muestra las líneas de sólo lectura', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->done()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit' => $product->unit->value]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('5,00');
});

test('una línea de artículo no producible se rechaza', function () {
    [$user, $tenant] = productionSetup();
    $notProducible = Product::factory()->for($tenant)->resale()->create();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $notProducible->id, 'quantity' => 1]],
        ])
        ->assertStatus(422);
});

test('una orden terminada rechaza agregar un pedido', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->done()->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->post(route('production-orders.requests.store', $order), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
        ])
        ->assertStatus(422);
});

test('el pedido admite un cliente como destino', function () {
    [$user, $tenant] = productionSetup();
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Kiosco La Esquina']);
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)->post(route('production-orders.requests.store', $order), [
        'destination_type' => 'customer',
        'destination_id' => $customer->id,
    ])->assertRedirect();

    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee('Kiosco La Esquina');
});

test('el endpoint de preview responde con el consumo agregado de la orden', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value, 'position' => 1]);

    $this->actingAs($user)
        ->getJson(route('production-orders.preview', $order))
        ->assertOk()
        ->assertJsonStructure(['lines', 'material_cost', 'labor_cost', 'total_cost']);
});

test('producir la orden confirmada la marca Done y redirige a su detalle', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => $product->unit->value]);

    $this->actingAs($user)
        ->post(route('production-orders.produce', $order))
        ->assertRedirect(route('production-orders.show', $order));

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Done);
});

test('anular la orden la marca Cancelled', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->patch(route('production-orders.cancel', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Cancelled);
});

test('confirmar la orden vía transition', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->patch(route('production-orders.transition', $order), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Confirmed);
});

test('viewer no puede crear una orden', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('production-orders.store'), ['type' => 'daily'])
        ->assertForbidden();
});

test('viewer puede ver el índice y el detalle de una orden', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)->get(route('production-orders.index'))->assertOk();
    $this->actingAs($user)->get(route('production-orders.show', $order))->assertOk();
});

test('aislamiento: owner no puede ver una orden de otro tenant', function () {
    [$user] = productionSetup();

    $otherTenant = Tenant::factory()->create();
    $otherOrder = ProductionOrder::factory()->for($otherTenant)->create(['location_id' => $otherTenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $otherOrder))
        ->assertNotFound();
});

test('aislamiento: un pedido de otra orden no se puede eliminar cruzado', function () {
    [$user, $tenant] = productionSetup();
    $orderA = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $orderB = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $requestOfB = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $orderB->id]);

    $this->actingAs($user)
        ->delete(route('production-orders.requests.destroy', [$orderA, $requestOfB]))
        ->assertNotFound();
});

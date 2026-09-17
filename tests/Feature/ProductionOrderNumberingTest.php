<?php

use App\Enums\DeliveryDestinationType;
use App\Models\ProductionOrder;
use App\Services\ProductionOrderDuplicator;
use App\Services\ProductionOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// stockTenantUser() es global (StockServiceTest); productionOrderService() y
// orderWithLine() son globales (ProductionOrderTest); productionSetup() y
// seedStock() son globales (ProductionControllerTest/ProductionTest).

function ordersService(): ProductionOrderService
{
    return app(ProductionOrderService::class);
}

test('la primera orden de un negocio es la número 1', function () {
    [, $tenant] = stockTenantUser();

    $order = ordersService()->createOrder($tenant, [
        'location_id' => $tenant->defaultLocation()->id,
        'type' => 'daily',
        'status' => 'draft',
        'is_template' => false,
    ]);

    expect($order->number)->toBe(1);
});

test('cada orden nueva toma el número siguiente del negocio', function () {
    [, $tenant] = stockTenantUser();

    $attrs = ['location_id' => $tenant->defaultLocation()->id, 'type' => 'daily', 'status' => 'draft', 'is_template' => false];
    $first = ordersService()->createOrder($tenant, $attrs);
    $second = ordersService()->createOrder($tenant, $attrs);
    $third = ordersService()->createOrder($tenant, $attrs);

    expect([$first->number, $second->number, $third->number])->toBe([1, 2, 3]);
});

test('la numeración de órdenes es independiente entre negocios', function () {
    [, $tenantA] = stockTenantUser();
    [, $tenantB] = stockTenantUser();

    $attrsFor = fn ($tenant) => ['location_id' => $tenant->defaultLocation()->id, 'type' => 'daily', 'status' => 'draft', 'is_template' => false];
    $orderA = ordersService()->createOrder($tenantA, $attrsFor($tenantA));
    $orderB1 = ordersService()->createOrder($tenantB, $attrsFor($tenantB));
    $orderB2 = ordersService()->createOrder($tenantB, $attrsFor($tenantB));

    expect($orderA->number)->toBe(1)
        ->and($orderB1->number)->toBe(1)
        ->and($orderB2->number)->toBe(2);
});

test('una plantilla no consume número de orden', function () {
    [, $tenant] = stockTenantUser();

    $template = ordersService()->createOrder($tenant, [
        'location_id' => $tenant->defaultLocation()->id,
        'type' => 'daily',
        'status' => 'draft',
        'is_template' => true,
        'name' => 'Plantilla de prueba',
    ]);
    $order = ordersService()->createOrder($tenant, [
        'location_id' => $tenant->defaultLocation()->id,
        'type' => 'daily',
        'status' => 'draft',
        'is_template' => false,
    ]);

    expect($template->number)->toBeNull()
        ->and($order->number)->toBe(1); // la plantilla no gastó el número 1
});

test('usar una plantilla numera la orden resultante', function () {
    [$user, $tenant] = stockTenantUser();
    $template = ProductionOrder::factory()->for($tenant)->template()->create([
        'location_id' => $tenant->defaultLocation()->id,
    ]);

    $copy = app(ProductionOrderDuplicator::class)->duplicate($template, $user, scheduledFor: now()->toDateString());

    expect($copy->is_template)->toBeFalse()
        ->and($copy->number)->toBe(1);
});

test('repetir una orden le da un número nuevo y no el del original', function () {
    [$user, $tenant] = stockTenantUser();
    $original = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $copy = app(ProductionOrderDuplicator::class)->duplicate($original, $user, scheduledFor: now()->addDay()->toDateString());

    expect($copy->number)->not->toBe($original->number)
        ->and($copy->number)->toBe(2);
});

test('dos órdenes del mismo negocio no pueden compartir número', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    expect(fn () => DB::table('production_orders')->insert([
        'tenant_id' => $tenant->id,
        'location_id' => $tenant->defaultLocation()->id,
        'type' => 'daily',
        'number' => $order->number,
        'status' => 'draft',
        'is_template' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('los pedidos de una orden se numeran 1, 2 y 3', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];
    $r1 = ordersService()->addRequest($order, $attrs);
    $r2 = ordersService()->addRequest($order, $attrs);
    $r3 = ordersService()->addRequest($order, $attrs);

    expect([$r1->position, $r2->position, $r3->position])->toBe([1, 2, 3]);
});

test('la numeración de pedidos arranca de nuevo en cada orden', function () {
    [, $tenant] = stockTenantUser();
    $orderA = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $orderB = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];
    ordersService()->addRequest($orderA, $attrs);
    $firstOfB = ordersService()->addRequest($orderB, $attrs);

    expect($firstOfB->position)->toBe(1);
});

test('duplicar una orden renumera sus pedidos desde 1', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];
    ordersService()->addRequest($order, $attrs);
    ordersService()->addRequest($order, $attrs);

    $copy = app(ProductionOrderDuplicator::class)->duplicate($order, $user, scheduledFor: now()->addDay()->toDateString());

    expect($copy->productionOrderRequests()->orderBy('position')->pluck('position')->all())->toBe([1, 2]);
});

test('borrar un pedido no reusa su número', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];
    $first = ordersService()->addRequest($order, $attrs);
    ordersService()->addRequest($order, $attrs);
    $first->delete();

    $third = ordersService()->addRequest($order, $attrs);

    expect($third->position)->toBe(3);
});

test('el índice de órdenes muestra el número', function () {
    [$user, $tenant] = stockTenantUser();
    ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee('Orden #1');
});

test('el detalle de la orden muestra Orden #N y el número propio de cada pedido', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $r1 = ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);
    $r2 = ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee('Orden #1')
        ->assertSee("Pedido #{$r1->number}")
        ->assertSee("Pedido #{$r2->number}");
});

test('la planilla de reparto muestra el número de orden y el del pedido', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', $order))
        ->assertOk()
        ->assertSee('Orden #1')
        ->assertSee("Pedido #{$request->number}");
});

// --- Numeración PROPIA del pedido (independiente de position/la orden) ---

test('el primer pedido del negocio es el número 1', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $request = ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    expect($request->number)->toBe(1);
});

test('la numeración de pedidos es correlativa por negocio a través de órdenes distintas', function () {
    [, $tenant] = stockTenantUser();
    $orderA = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $orderB = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];

    $r1 = ordersService()->addRequest($orderA, $attrs);
    $r2 = ordersService()->addRequest($orderB, $attrs);
    $r3 = ordersService()->addRequest($orderA, $attrs);

    expect([$r1->number, $r2->number, $r3->number])->toBe([1, 2, 3])
        ->and([$r1->position, $r2->position, $r3->position])->toBe([1, 1, 2]); // position sigue local a su orden
});

test('la numeración de pedidos es independiente entre negocios', function () {
    [, $tenantA] = stockTenantUser();
    [, $tenantB] = stockTenantUser();
    $orderA = ProductionOrder::factory()->for($tenantA)->create(['location_id' => $tenantA->defaultLocation()->id]);
    $orderB = ProductionOrder::factory()->for($tenantB)->create(['location_id' => $tenantB->defaultLocation()->id]);

    $requestA = ordersService()->addRequest($orderA, ['destination_type' => 'location', 'destination_id' => $tenantA->defaultLocation()->id]);
    $requestB = ordersService()->addRequest($orderB, ['destination_type' => 'location', 'destination_id' => $tenantB->defaultLocation()->id]);

    expect($requestA->number)->toBe(1)
        ->and($requestB->number)->toBe(1);
});

test('el pedido único de una orden instantánea también numera', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);

    $order = ordersService()->produceInstant(
        $tenant, $user, DeliveryDestinationType::Location, $tenant->defaultLocation()->id,
        collect([['product' => $product, 'quantity' => 1]]),
    );

    expect($order->productionOrderRequests()->first()->number)->toBe(1);
});

test('una plantilla no consume número de pedido, pero usarla sí', function () {
    [$user, $tenant] = stockTenantUser();
    $template = ProductionOrder::factory()->for($tenant)->template()->create(['location_id' => $tenant->defaultLocation()->id]);
    $templateRequest = ordersService()->addRequest($template, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    expect($templateRequest->number)->toBeNull();

    $copy = app(ProductionOrderDuplicator::class)->duplicate($template, $user, scheduledFor: now()->toDateString());

    expect($copy->productionOrderRequests()->first()->number)->toBe(1); // la plantilla no gastó el 1
});

test('borrar un pedido no reusa su número propio', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $attrs = ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id];
    $first = ordersService()->addRequest($order, $attrs);
    $first->delete();

    $second = ordersService()->addRequest($order, $attrs);

    expect($second->number)->toBe(2);
});

test('numberLabel dice Pedido #N, y cae a Pedido {position} si number es null', function () {
    [, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    expect($request->numberLabel())->toBe("Pedido #{$request->number}");

    $request->forceFill(['number' => null])->save();
    expect($request->fresh()->numberLabel())->toBe("Pedido {$request->position}");
});

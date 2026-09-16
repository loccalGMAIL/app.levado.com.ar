<?php

use App\Models\ProductionOrder;
use App\Services\ProductionOrderDuplicator;
use App\Services\ProductionOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// stockTenantUser() es global (StockServiceTest); productionOrderService() y
// orderWithLine() son globales (ProductionOrderTest).

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

test('el detalle de la orden muestra Orden #N y la etiqueta de cada pedido', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);
    ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $order))
        ->assertOk()
        ->assertSee('Orden #1')
        ->assertSee('Pedido 1')
        ->assertSee('Pedido 2');
});

test('la planilla de reparto muestra el número de orden', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    ordersService()->addRequest($order, ['destination_type' => 'location', 'destination_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->get(route('production-orders.delivery-sheet', $order))
        ->assertOk()
        ->assertSee('Orden #1')
        ->assertSee('Pedido 1');
});

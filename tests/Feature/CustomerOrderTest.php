<?php

use App\Enums\ContainerType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Customer;
use App\Models\CustomerOrderLine;
use App\Models\CustomerOrderWeek;
use App\Models\DeliveryPerson;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Services\CustomerOrderTotals;
use App\Services\OrderSheetWeekService;
use Carbon\CarbonImmutable;

// "Hoy": miércoles 18/11/2026 → la semana actual empieza el lunes 16/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
});

/** Pedido de un cliente: una fila con sus cantidades de lunes a domingo. */
function customerOrder(Customer $customer, Product $product, array $quantities, string $monday = '2026-11-16', ?ContainerType $container = null): CustomerOrderLine
{
    $week = CustomerOrderWeek::query()->where('customer_id', $customer->id)->whereDate('starts_on', $monday)->first()
        ?? CustomerOrderWeek::factory()->for($customer)->startingOn($monday)->create();

    return CustomerOrderLine::factory()->for($week, 'week')->withQuantities($quantities)->create([
        'product_id' => $product->id,
        'container_type' => $container,
    ]);
}

/** Elaborado cuya receta se pide en latas de $per unidades. */
function tinProduct(Tenant $tenant, float $per = 30, string $name = 'Medialunas'): Product
{
    $recipe = Recipe::factory()->for($tenant)->inContainers(ContainerType::Tin, $per)->create(['yield_unit' => Unit::Unidad->value]);

    return Product::factory()->for($tenant)->manufactured()->create(['name' => $name, 'recipe_id' => $recipe->id, 'unit' => Unit::Unidad->value]);
}

// --- Semana por cliente ---

test('owner guarda la semana de un cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create();

    $this->actingAs($user)
        ->put(route('reparto.pedidos.semana.update', $customer), [
            'start' => '2026-11-18',
            'lines' => [['product_id' => $product->id, 'quantities' => [10, 0, 5, '', '', '', 2]]],
        ])
        ->assertRedirect(route('reparto.pedidos.index', ['customer' => $customer->id, 'start' => '2026-11-16']));

    $line = CustomerOrderLine::query()->firstOrFail();
    expect($line->quantities())->toBe([10.0, 0.0, 5.0, 0.0, 0.0, 0.0, 2.0])
        ->and($line->week->starts_on->toDateString())->toBe('2026-11-16');
});

test('una semana nueva copia la última cargada del cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create();
    customerOrder($customer, $product, [4, 4, 4, 4, 4, 0, 0], '2026-11-09');

    $this->actingAs($user)
        ->get(route('reparto.pedidos.index', ['customer' => $customer->id, 'start' => '2026-11-16']))
        ->assertOk();

    $copy = CustomerOrderWeek::query()->whereDate('starts_on', '2026-11-16')->firstOrFail();
    expect($copy->lines)->toHaveCount(1)
        ->and($copy->lines->first()->quantities())->toBe([4.0, 4.0, 4.0, 4.0, 4.0, 0.0, 0.0]);
});

test('volver a copiar la semana anterior descarta lo cargado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create();
    customerOrder($customer, $product, [1, 1, 1, 1, 1, 1, 1], '2026-11-09');
    customerOrder($customer, $product, [9, 9, 9, 9, 9, 9, 9], '2026-11-16');

    $this->actingAs($user)
        ->post(route('reparto.pedidos.semana.recopy', $customer), ['start' => '2026-11-16'])
        ->assertRedirect();

    $line = CustomerOrderWeek::query()->whereDate('starts_on', '2026-11-16')->firstOrFail()->lines->first();
    expect($line->quantities())->toBe([1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0]);
});

test('un artículo con rinde se guarda en latas y exige cuartos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create();
    $product = tinProduct($tenant);

    $this->actingAs($user)
        ->put(route('reparto.pedidos.semana.update', $customer), [
            'start' => '2026-11-16',
            'lines' => [['product_id' => $product->id, 'quantities' => [1.1, 0, 0, 0, 0, 0, 0]]],
        ])
        ->assertSessionHasErrors();

    $this->actingAs($user)
        ->put(route('reparto.pedidos.semana.update', $customer), [
            'start' => '2026-11-16',
            'lines' => [['product_id' => $product->id, 'quantities' => [1.25, 0, 0, 0, 0, 0, 0]]],
        ])
        ->assertSessionHasNoErrors();

    expect(CustomerOrderLine::query()->firstOrFail()->container_type)->toBe(ContainerType::Tin);
});

test('no se puede guardar la semana de un cliente de otro negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $foreign = Customer::factory()->create();

    $this->actingAs($user)
        ->put(route('reparto.pedidos.semana.update', $foreign), ['start' => '2026-11-16', 'lines' => []])
        ->assertNotFound();
});

test('un viewer no accede a los pedidos de clientes', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)->get(route('reparto.pedidos.index'))->assertForbidden();
});

// --- Totales por planilla ---

test('lo que piden los clientes suma en la planilla de destino de cada artículo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $diarios = OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    $tortas = OrderSheet::factory()->for($tenant)->create(['name' => 'Tortas']);
    $facturas = Product::factory()->for($tenant)->manufactured()->create(['order_sheet_id' => $diarios->id]);
    $torta = Product::factory()->for($tenant)->manufactured()->create(['order_sheet_id' => $tortas->id]);
    $suelto = Product::factory()->for($tenant)->manufactured()->create();

    $kiosco = Customer::factory()->for($tenant)->create();
    $bar = Customer::factory()->for($tenant)->create();
    customerOrder($kiosco, $facturas, [36, 36, 24, 0, 0, 0, 0]);
    customerOrder($bar, $facturas, [10, 0, 0, 0, 0, 0, 0]);
    customerOrder($kiosco, $torta, [1, 0, 0, 0, 0, 0, 0]);
    customerOrder($kiosco, $suelto, [7, 0, 0, 0, 0, 0, 0]);

    $totals = app(CustomerOrderTotals::class)->forSheet($diarios, CarbonImmutable::parse('2026-11-16'));

    expect($totals->keys()->all())->toBe([$facturas->id])
        ->and($totals[$facturas->id]['units'])->toBe([46.0, 36.0, 24.0, 0.0, 0.0, 0.0, 0.0]);
});

test('los clientes inactivos y las otras semanas no suman', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create(['order_sheet_id' => $sheet->id]);

    customerOrder(Customer::factory()->for($tenant)->inactive()->create(), $product, [50, 0, 0, 0, 0, 0, 0]);
    customerOrder(Customer::factory()->for($tenant)->create(), $product, [60, 0, 0, 0, 0, 0, 0], '2026-11-23');
    customerOrder(Customer::factory()->for($tenant)->create(), $product, [3, 0, 0, 0, 0, 0, 0]);

    $totals = app(CustomerOrderTotals::class)->forSheet($sheet, CarbonImmutable::parse('2026-11-16'));

    expect($totals[$product->id]['units'][0])->toBe(3.0);
});

test('los pedidos en latas y en unidades se suman en unidades', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $product = tinProduct($tenant, 30);
    $product->update(['order_sheet_id' => $sheet->id]);

    customerOrder(Customer::factory()->for($tenant)->create(), $product, [2, 0, 0, 0, 0, 0, 0], container: ContainerType::Tin);
    customerOrder(Customer::factory()->for($tenant)->create(), $product, [15, 0, 0, 0, 0, 0, 0]);

    $totals = app(CustomerOrderTotals::class)->forSheet($sheet, CarbonImmutable::parse('2026-11-16'));

    expect($totals[$product->id]['units'][0])->toBe(75.0);
});

test('la planilla muestra lo de clientes aparte y agrega los artículos que no estaban', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $listed = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Pan', 'order_sheet_id' => $sheet->id]);
    $new = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Facturas', 'order_sheet_id' => $sheet->id]);
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([48])->create(['product_id' => $listed->id]);
    $customer = Customer::factory()->for($tenant)->create();
    customerOrder($customer, $listed, [36]);
    customerOrder($customer, $new, [0, 12]);

    $start = CarbonImmutable::parse('2026-11-16');
    $presented = app(OrderSheetWeekService::class)->presentLine($week->lines()->with('product.recipe')->first());
    $rows = app(CustomerOrderTotals::class)->overlay($sheet, $start, [$presented]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['q'][0])->toBe(48.0)
        ->and($rows[0]['clients'][0])->toBe(36.0)
        ->and($rows[1]['name'])->toBe('Facturas')
        ->and($rows[1]['q'])->toBe(array_fill(0, 7, 0.0))
        ->and($rows[1]['clients'][1])->toBe(12.0);
});

test('guardar la semana de la planilla no pisa lo que piden los clientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create(['order_sheet_id' => $sheet->id]);
    $customerLine = customerOrder(Customer::factory()->for($tenant)->create(), $product, [36]);

    $this->actingAs($user)
        ->put(route('pedidos.semana.update', $sheet), [
            'start' => '2026-11-16',
            'lines' => [['product_id' => $product->id, 'quantities' => [48, 0, 0, 0, 0, 0, 0]]],
        ])
        ->assertSessionHasNoErrors();

    expect(OrderSheetLine::query()->firstOrFail()->quantities()[0])->toBe(48.0)
        ->and($customerLine->fresh()->quantities()[0])->toBe(36.0);
});

test('la planilla impresa muestra el total: lo manual más lo de clientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $product = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Facturas', 'order_sheet_id' => $sheet->id]);
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([48])->create(['product_id' => $product->id]);
    customerOrder(Customer::factory()->for($tenant)->create(), $product, [36]);

    $this->actingAs($user)
        ->get(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSeeInOrder(['Facturas', '84']);
});

test('la planilla impresa sin semana manual igual muestra lo que piden los clientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Facturas', 'order_sheet_id' => $sheet->id]);
    customerOrder(Customer::factory()->for($tenant)->create(), $product, [36]);

    $this->actingAs($user)
        ->get(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSeeInOrder(['Facturas', '36']);
});

// --- Planilla de destino del artículo ---

test('al guardar una planilla, los artículos sin destino pasan a sumar ahí y los que ya tienen otro se respetan', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $diarios = OrderSheet::factory()->for($tenant)->create();
    $tortas = OrderSheet::factory()->for($tenant)->create();
    $sinDestino = Product::factory()->for($tenant)->manufactured()->create();
    $conOtro = Product::factory()->for($tenant)->manufactured()->create(['order_sheet_id' => $tortas->id]);

    $this->actingAs($user)
        ->put(route('pedidos.semana.update', $diarios), [
            'start' => '2026-11-16',
            'lines' => [
                ['product_id' => $sinDestino->id, 'quantities' => [1, 0, 0, 0, 0, 0, 0]],
                ['product_id' => $conOtro->id, 'quantities' => [1, 0, 0, 0, 0, 0, 0]],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($sinDestino->fresh()->order_sheet_id)->toBe($diarios->id)
        ->and($conOtro->fresh()->order_sheet_id)->toBe($tortas->id);
});

test('owner asigna la planilla de destino de un artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value]);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'resale',
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => 10,
            'order_sheet_id' => $sheet->id,
        ])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->order_sheet_id)->toBe($sheet->id);
});

test('no se puede apuntar un artículo a la planilla de otro negocio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $foreign = OrderSheet::factory()->create();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value]);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'resale',
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => 10,
            'order_sheet_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('order_sheet_id');
});

// --- Hoja diaria del repartidor ---

test('la hoja del repartidor lista sus clientes por orden de recorrido y lo que hay que cargar', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pedro = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Pedro']);
    $otro = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Otro']);
    $pan = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Pan francés']);
    $facturas = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Facturas']);

    $primero = Customer::factory()->for($tenant)->create(['name' => 'Zeta Kiosco', 'delivery_person_id' => $pedro->id, 'route_position' => 1]);
    $segundo = Customer::factory()->for($tenant)->create(['name' => 'Alfa Almacén', 'delivery_person_id' => $pedro->id, 'route_position' => 2]);
    $ajeno = Customer::factory()->for($tenant)->create(['name' => 'Cliente Ajeno', 'delivery_person_id' => $otro->id]);
    $sinPedido = Customer::factory()->for($tenant)->create(['name' => 'Sin Pedido Hoy', 'delivery_person_id' => $pedro->id]);

    // Miércoles = índice 2.
    customerOrder($primero, $pan, [0, 0, 10]);
    customerOrder($segundo, $pan, [0, 0, 5]);
    customerOrder($segundo, $facturas, [0, 0, 12]);
    customerOrder($ajeno, $pan, [0, 0, 99]);
    customerOrder($sinPedido, $pan, [7, 0, 0]);

    $this->actingAs($user)
        ->get(route('reparto.hoja', ['date' => '2026-11-18', 'delivery_person' => $pedro->id]))
        ->assertOk()
        ->assertSeeInOrder(['Reparto — Pedro', 'Miércoles 18/11/2026', 'Zeta Kiosco', 'Alfa Almacén', 'CARGAR', '15', '12'])
        ->assertDontSee('Cliente Ajeno')
        ->assertDontSee('Sin Pedido Hoy');
});

test('la hoja de clientes sin repartidor se pide sin delivery_person', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pan = Product::factory()->for($tenant)->manufactured()->create();
    customerOrder(Customer::factory()->for($tenant)->create(['name' => 'Huérfano']), $pan, [0, 0, 3]);

    $this->actingAs($user)
        ->get(route('reparto.hoja', ['date' => '2026-11-18']))
        ->assertOk()
        ->assertSeeInOrder(['Reparto — Sin repartidor', 'Huérfano']);
});

test('en la hoja un artículo en latas muestra latas y unidades', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = tinProduct($tenant, 30);
    $customer = Customer::factory()->for($tenant)->create();
    customerOrder($customer, $product, [0, 0, 2], container: ContainerType::Tin);

    $this->actingAs($user)
        ->get(route('reparto.hoja', ['date' => '2026-11-18']))
        ->assertOk()
        ->assertSee('Lata de 30')
        ->assertSee('2 latas (60');
});

test('sin pedidos la hoja sale vacía', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->get(route('reparto.hoja', ['date' => '2026-11-18']))
        ->assertOk()
        ->assertSee('Sin pedidos de clientes para este día.');
});

test('el PDF de la hoja se descarga en application/pdf', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pan = Product::factory()->for($tenant)->manufactured()->create();
    customerOrder(Customer::factory()->for($tenant)->create(), $pan, [0, 0, 3]);

    $this->actingAs($user)
        ->get(route('reparto.hoja.pdf', ['date' => '2026-11-18']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('no se puede pedir la hoja de un repartidor de otro negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $foreign = DeliveryPerson::factory()->create();

    $this->actingAs($user)
        ->get(route('reparto.hoja', ['date' => '2026-11-18', 'delivery_person' => $foreign->id]))
        ->assertNotFound();
});

test('owner guarda el orden en el recorrido de un cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('reparto.clientes.store'), ['name' => 'Con orden', 'route_position' => 3])
        ->assertSessionHasNoErrors();

    expect($tenant->customers()->where('name', 'Con orden')->firstOrFail()->route_position)->toBe(3);
});

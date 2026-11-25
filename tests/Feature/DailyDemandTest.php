<?php

use App\Enums\ContainerType;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Models\Customer;
use App\Models\CustomerOrderLine;
use App\Models\CustomerOrderWeek;
use App\Models\OrderSheet;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Services\DailyDemand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// "Hoy": miércoles 25/11/2026 → la semana actual empieza el lunes 23/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

function demandCustomerOrder(Customer $customer, Product $product, array $quantities, string $monday, ?ContainerType $container = null): void
{
    $week = CustomerOrderWeek::query()->where('customer_id', $customer->id)->whereDate('starts_on', $monday)->first()
        ?? CustomerOrderWeek::factory()->for($customer)->startingOn($monday)->create();

    CustomerOrderLine::factory()->for($week, 'week')->withQuantities($quantities)->create([
        'product_id' => $product->id,
        'container_type' => $container,
    ]);
}

function demandFor($tenant, string $date): Collection
{
    return app(DailyDemand::class)->forDate($tenant->id, CarbonImmutable::parse($date));
}

test('suma lo manual de las planillas y lo de los clientes', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    $sheet = sheetOrder($tenant, $medialunas, [0, 0, 40, 0, 0, 0, 0], '2026-11-23');
    sheetOrder($tenant, $medialunas, [0, 0, 10, 0, 0, 0, 0], '2026-11-23');
    demandCustomerOrder(Customer::factory()->for($tenant)->create(), $medialunas, [0, 0, 6, 0, 0, 0, 0], '2026-11-23');

    $entry = demandFor($tenant, '2026-11-25')->get($medialunas->id);

    expect($entry['manual'])->toBe(50.0)
        ->and($entry['clients'])->toBe(6.0)
        ->and($entry['units'])->toBe(56.0);
});

test('las presentaciones suman al artículo base', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    $pack = Product::factory()->for($tenant)->create([
        'name' => 'Pack x6',
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $medialunas->recipe_id,
        'recipe_quantity' => 6,
        'cost_per_unit' => null,
    ]);
    sheetOrder($tenant, $pack, [0, 0, 2, 0, 0, 0, 0], '2026-11-23');
    demandCustomerOrder(Customer::factory()->for($tenant)->create(), $pack, [0, 0, 1, 0, 0, 0, 0], '2026-11-23');

    $demand = demandFor($tenant, '2026-11-25');

    expect($demand->keys()->all())->toBe([$medialunas->id])
        ->and($demand->get($medialunas->id)['units'])->toBe(18.0);
});

test('lo tipeado en latas se pasa a unidades', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pan = doughProduct($tenant, 'Pan francés', tinPer: 30);
    sheetOrder($tenant, $pan, [0, 0, 2.5, 0, 0, 0, 0], '2026-11-23', container: ContainerType::Tin);

    expect(demandFor($tenant, '2026-11-25')->get($pan->id)['units'])->toBe(75.0);
});

test('leer la demanda no crea semanas: usa lo que tendría la copia', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    $customer = Customer::factory()->for($tenant)->create();
    // Solo hay semanas cargadas hasta la del 16/11: la del 30/11 no existe.
    sheetOrder($tenant, $medialunas, [20, 20, 20, 20, 20, 0, 0], '2026-11-16');
    demandCustomerOrder($customer, $medialunas, [3, 3, 3, 3, 3, 0, 0], '2026-11-16');

    $entry = demandFor($tenant, '2026-11-30')->get($medialunas->id);

    expect($entry['units'])->toBe(23.0)
        ->and(OrderSheetWeek::query()->count())->toBe(1)
        ->and(CustomerOrderWeek::query()->count())->toBe(1);
});

test('una semana pasada sin datos no suma', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    sheetOrder($tenant, $medialunas, [20, 20, 20, 20, 20, 0, 0], '2026-11-02');

    expect(demandFor($tenant, '2026-11-10'))->toBeEmpty();
});

test('el domingo lee el lunes de la semana siguiente', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    $sheet = sheetOrder($tenant, $medialunas, [0, 0, 0, 0, 0, 0, 5], '2026-11-23');
    sheetOrder($tenant, $medialunas, [9, 0, 0, 0, 0, 0, 0], '2026-11-30', $sheet);

    expect(demandFor($tenant, '2026-11-29')->get($medialunas->id)['units'])->toBe(5.0)
        ->and(demandFor($tenant, '2026-11-30')->get($medialunas->id)['units'])->toBe(9.0);
});

test('un cliente inactivo o una planilla pausada no suman', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    sheetOrder($tenant, $medialunas, [0, 0, 40, 0, 0, 0, 0], '2026-11-23', OrderSheet::factory()->for($tenant)->inactive()->create());
    demandCustomerOrder(Customer::factory()->for($tenant)->create(['active' => false]), $medialunas, [0, 0, 6, 0, 0, 0, 0], '2026-11-23');

    expect(demandFor($tenant, '2026-11-25'))->toBeEmpty();
});

test('solo cuentan los artículos producibles', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $cafe = doughProduct($tenant, 'Café');
    $cafe->update(['product_category_id' => $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false])->id]);
    sheetOrder($tenant, $cafe, [0, 0, 10, 0, 0, 0, 0], '2026-11-23');

    expect(demandFor($tenant, '2026-11-25'))->toBeEmpty();
});

test('la demanda es de cada negocio', function () {
    [, $tenantA] = tenantUserAs(TenantUserRole::Owner);
    [, $tenantB] = tenantUserAs(TenantUserRole::Owner);
    sheetOrder($tenantA, doughProduct($tenantA, 'Medialunas'), [0, 0, 10, 0, 0, 0, 0], '2026-11-23');

    expect(demandFor($tenantB, '2026-11-25'))->toBeEmpty();
});

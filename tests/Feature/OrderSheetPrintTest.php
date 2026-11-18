<?php

use App\Enums\ContainerType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

// "Hoy": miércoles 18/11/2026 → la semana actual empieza el lunes 16/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
});

test('la vista de impresión muestra la planilla, la semana y los artículos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $product = Product::factory()->for($tenant)->create(['name' => 'Pan francés']);
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([20])->create(['product_id' => $product->id]);

    $this->actingAs($user)
        ->get(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSeeInOrder(['Pedidos — Diarios', 'Semana del 16/11/2026 al 22/11/2026', 'Pan francés', '20']);
});

test('un artículo en latas muestra el recipiente y el total en latas y unidades', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $recipe = Recipe::factory()->for($tenant)->inContainers(ContainerType::Tin, 30)->create(['yield_unit' => Unit::Unidad->value]);
    $product = Product::factory()->for($tenant)->manufactured()->create(['name' => 'Medialunas', 'recipe_id' => $recipe->id, 'unit' => Unit::Unidad->value]);
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([2])->create(['product_id' => $product->id, 'container_type' => ContainerType::Tin]);

    $this->actingAs($user)
        ->get(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSeeInOrder(['Medialunas', 'Lata de 30', '2', '60'])
        ->assertSee('2 latas · 60');
});

test('el PDF se descarga en application/pdf', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([5])->create();

    $this->actingAs($user)
        ->get(route('pedidos.imprimir.pdf', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('un viewer puede imprimir la planilla', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Secos']);

    $this->actingAs($user)->get(route('pedidos.imprimir', $sheet))->assertOk()->assertSee('Pedidos — Secos');
});

test('una planilla de otro negocio resuelve 404 al imprimir', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $ajena = OrderSheet::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($user)->get(route('pedidos.imprimir', $ajena))->assertNotFound();
    $this->actingAs($user)->get(route('pedidos.imprimir.pdf', $ajena))->assertNotFound();
});

test('imprimir una semana sin cargar no crea la semana y avisa que está vacía', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $previous = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-09')->create();
    OrderSheetLine::factory()->for($previous, 'week')->withQuantities([8])->create();

    // Semana actual: en pantalla se crearía copiando la anterior; imprimir no.
    $this->actingAs($user)
        ->get(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSee('Sin pedidos cargados para esta semana');

    expect($sheet->weeks()->count())->toBe(1);
});

test('la pantalla de pedidos tiene el link para imprimir la semana abierta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $sheet = OrderSheet::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('pedidos.index', ['sheet' => $sheet->id, 'start' => '2026-11-16']))
        ->assertOk()
        ->assertSee(route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => '2026-11-16']), false);
});

<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Recipe;
use App\Services\DoughPlanner;
use Carbon\CarbonImmutable;

// "Hoy": miércoles 25/11/2026 → la semana actual empieza el lunes 23/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

function planFor($tenant, string $date = '2026-11-25'): array
{
    return app(DoughPlanner::class)->forDate($tenant->defaultLocation(), CarbonImmutable::parse($date));
}

test('lo de cámara se planea con lo pedido para mañana y lo directo con lo de hoy', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas', proofing: true);
    $galletitas = doughProduct($tenant, 'Galletitas', proofing: false);
    // Mié 25 / Jue 26.
    sheetOrder($tenant, $medialunas, [0, 0, 10, 48, 0, 0, 0], '2026-11-23');
    sheetOrder($tenant, $galletitas, [0, 0, 30, 99, 0, 0, 0], '2026-11-23');

    $plan = planFor($tenant);

    expect($plan['chamber'])->toHaveCount(1)
        ->and($plan['chamber'][0])->toMatchArray(['name' => 'Medialunas', 'ordered' => 48.0, 'in_chamber' => 0.0, 'to_make' => 48.0])
        ->and($plan['direct'])->toHaveCount(1)
        ->and($plan['direct'][0])->toMatchArray(['name' => 'Galletitas', 'ordered' => 30.0, 'to_make' => 30.0]);
});

test('lo que ya está en cámara se descuenta, con piso cero', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $medialunas = doughProduct($tenant, 'Medialunas');
    $facturas = doughProduct($tenant, 'Facturas');
    sheetOrder($tenant, $medialunas, [0, 0, 0, 48, 0, 0, 0], '2026-11-23');
    sheetOrder($tenant, $facturas, [0, 0, 0, 10, 0, 0, 0], '2026-11-23');
    seedChamber($medialunas, 20, $user);
    seedChamber($facturas, 15, $user);
    // Stock en la sucursal no cuenta como cámara.
    seedStock($medialunas, 100, $user);

    $rows = collect(planFor($tenant)['chamber'])->keyBy('name');

    expect($rows['Medialunas'])->toMatchArray(['in_chamber' => 20.0, 'to_make' => 28.0])
        ->and($rows['Facturas'])->toMatchArray(['in_chamber' => 15.0, 'to_make' => 0.0]);
});

test('las latas se redondean hacia arriba de a cuartos', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pan = doughProduct($tenant, 'Pan francés', tinPer: 30);
    sheetOrder($tenant, $pan, [0, 0, 0, 65, 0, 0, 0], '2026-11-23');

    $row = planFor($tenant)['chamber'][0];

    expect($row['to_make'])->toBe(65.0)
        ->and($row['containers'])->toBe(2.25)
        ->and($row['container']['type'])->toBe('lata');
});

test('las masas compartidas se suman en la unidad de rinde de la sub-receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $hojaldre = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'name' => 'Masa de hojaldre', 'yield_quantity' => 10, 'yield_unit' => Unit::Kilogramo->value,
    ]);
    $medialunas = doughProduct($tenant, 'Medialunas', yield: 12);
    $croissants = doughProduct($tenant, 'Croissants', yield: 10);
    $pan = doughProduct($tenant, 'Pan casero', proofing: false);
    $medialunas->recipe->subrecipeLines()->create(['child_recipe_id' => $hojaldre->id, 'quantity_used' => 2, 'unit' => Unit::Kilogramo->value]);
    $croissants->recipe->subrecipeLines()->create(['child_recipe_id' => $hojaldre->id, 'quantity_used' => 1500, 'unit' => Unit::Gramo->value]);
    sheetOrder($tenant, $medialunas, [0, 0, 0, 24, 0, 0, 0], '2026-11-23');
    sheetOrder($tenant, $croissants, [0, 0, 0, 20, 0, 0, 0], '2026-11-23');
    sheetOrder($tenant, $pan, [0, 0, 5, 0, 0, 0, 0], '2026-11-23');

    $plan = planFor($tenant);

    // 2 kg × 24/12 + 1,5 kg × 20/10 = 4 + 3.
    expect($plan['masses'])->toHaveCount(1)
        ->and($plan['masses'][0])->toMatchArray(['name' => 'Masa de hojaldre', 'quantity' => 7.0, 'unit' => 'kg'])
        ->and($plan['masses'][0]['articles'])->toBe(['Croissants', 'Medialunas'])
        ->and($plan['without_mass'])->toBe(['Pan casero']);
});

test('lo que no hay que amasar no pide masa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $hojaldre = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 10, 'yield_unit' => Unit::Kilogramo->value]);
    $medialunas = doughProduct($tenant, 'Medialunas', yield: 12);
    $medialunas->recipe->subrecipeLines()->create(['child_recipe_id' => $hojaldre->id, 'quantity_used' => 2, 'unit' => Unit::Kilogramo->value]);
    sheetOrder($tenant, $medialunas, [0, 0, 0, 24, 0, 0, 0], '2026-11-23');
    seedChamber($medialunas, 30, $user);

    $plan = planFor($tenant);

    expect($plan['masses'])->toBeEmpty()
        ->and($plan['without_mass'])->toBeEmpty();
});

test('leer la planilla no crea la cámara', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    sheetOrder($tenant, doughProduct($tenant), [0, 0, 0, 24, 0, 0, 0], '2026-11-23');

    planFor($tenant);

    expect($tenant->existingProofingChamber())->toBeNull();
});

test('cualquier rol ve la planilla de amasado, la imprime y baja el PDF', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $medialunas = doughProduct($tenant, 'Medialunas', tinPer: 30);
    sheetOrder($tenant, $medialunas, [0, 0, 0, 65, 0, 0, 0], '2026-11-23');

    $this->actingAs($user)
        ->get(route('produccion.amasado.index', ['fecha' => '2026-11-25']))
        ->assertOk()
        ->assertSee('Va a cámara')
        ->assertSee('Medialunas')
        ->assertSee('2,25 latas');

    $this->actingAs($user)
        ->get(route('produccion.amasado.imprimir', ['fecha' => '2026-11-25']))
        ->assertOk()
        ->assertSee('Amasado — 25/11/2026');

    $this->actingAs($user)
        ->get(route('produccion.amasado.imprimir.pdf', ['fecha' => '2026-11-25']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

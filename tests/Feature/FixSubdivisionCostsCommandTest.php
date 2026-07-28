<?php

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Tenant;

/**
 * El comando aplica dos correcciones OPUESTAS y la clasificación es lo único que
 * decide cuál. Estos tests anclan esa frontera: que el caso A gane sobre el B, y
 * que el redondeo de un costo cargado a mano no se confunda con desincronización.
 */
function fixCommandTenant(): Tenant
{
    return Tenant::factory()->create();
}

test('caso A: el envase cuyo costo nunca se dividió se corrige dividiendo por las subdivisiones', function () {
    $tenant = fixCommandTenant();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'cost_per_unit' => 2000,
        'cost_per_package' => 2000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'yes')
        ->assertSuccessful();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(20.0)
        ->and((float) $packaging->cost_per_package)->toBe(2000.0);
});

test('caso A también cubre el ítem sin precio por envase registrado', function () {
    $tenant = fixCommandTenant();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 50,
        'cost_per_unit' => 4000,
        'cost_per_package' => null,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'yes')
        ->assertSuccessful();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(80.0)
        ->and((float) $packaging->cost_per_package)->toBe(4000.0);
});

test('caso B: el precio por envase viejo se recalcula sin tocar el costo por sub-unidad', function () {
    $tenant = fixCommandTenant();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'subdivisions' => 24,
        'cost_per_unit' => 672.80,   // viene de la última compra: es la fuente de verdad
        'cost_per_package' => 1153.50, // quedó del precio anterior
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'ingredient'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'yes')
        ->assertSuccessful();

    $ingredient->refresh();

    expect((float) $ingredient->cost_per_unit)->toBe(672.80)
        ->and((float) $ingredient->cost_per_package)->toBe(16147.20);
});

test('un costo por sub-unidad redondeado a mano no se toma como desincronizado', function () {
    // 48000 / 360 = 133,3333 y el usuario cargó 133,30: es redondeo, no un bug.
    $tenant = fixCommandTenant();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'subdivisions' => 360,
        'cost_per_unit' => 133.30,
        'cost_per_package' => 48000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'ingredient'])
        ->assertSuccessful()
        ->expectsOutputToContain('No hay ingredientes ni envases pendientes de corregir.');

    $ingredient->refresh();

    expect((float) $ingredient->cost_per_unit)->toBe(133.30)
        ->and((float) $ingredient->cost_per_package)->toBe(48000.0);
});

test('un ítem coherente no aparece como candidato', function () {
    $tenant = fixCommandTenant();
    Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'cost_per_unit' => 20,
        'cost_per_package' => 2000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->assertSuccessful()
        ->expectsOutputToContain('No hay ingredientes ni envases pendientes de corregir.');
});

test('cancelar la confirmación no modifica nada', function () {
    $tenant = fixCommandTenant();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'cost_per_unit' => 2000,
        'cost_per_package' => 2000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'no')
        ->assertSuccessful();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(2000.0)
        ->and((float) $packaging->cost_per_package)->toBe(2000.0);
});

test('corregir el costo deja registro en el historial de precios', function () {
    $tenant = fixCommandTenant();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'cost_per_unit' => 2000,
        'cost_per_package' => 2000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'yes')
        ->assertSuccessful();

    expect((float) $packaging->priceLogs()->latest('recorded_at')->first()->cost_per_unit)->toBe(20.0);
});

test('corregir sólo el precio por envase no genera entrada en el historial', function () {
    // El costo por sub-unidad no cambió: el historial es append-only y no hay nada que anotar.
    $tenant = fixCommandTenant();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 50,
        'cost_per_unit' => 55,
        'cost_per_package' => 4000,
    ]);

    $this->artisan('ingredients:fix-subdivision-costs', ['--type' => 'packaging'])
        ->expectsConfirmation('¿Confirmar la corrección de todos los ítems listados?', 'yes')
        ->assertSuccessful();

    $packaging->refresh();

    expect($packaging->priceLogs()->count())->toBe(0)
        ->and((float) $packaging->cost_per_package)->toBe(2750.0);
});

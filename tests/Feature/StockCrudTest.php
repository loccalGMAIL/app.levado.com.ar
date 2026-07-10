<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\StockService;

function stockCrudUser(TenantUserRole $role = TenantUserRole::Owner): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

// --- Listado ---

test('viewer puede ver el listado de existencias en ambas pestañas', function () {
    [$user, $tenant] = stockCrudUser(TenantUserRole::Viewer);
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000']);
    Packaging::factory()->for($tenant)->create(['name' => 'Caja pizza']);

    $this->actingAs($user)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertSee('Harina 000');

    $this->actingAs($user)
        ->get(route('stock.index', ['type' => 'packaging']))
        ->assertOk()
        ->assertSee('Caja pizza');
});

test('el listado muestra la alerta de bajo mínimo', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Levadura', 'unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    $service = app(StockService::class);
    $service->registerAdjustment($ingredient, $location, 50, 'Carga inicial', $user);
    $service->setMinQuantity($ingredient, $location, 100);

    $this->actingAs($user)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertSee('Bajo mínimo');
});

test('el listado marca el stock negativo', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), -30, 'Salida sin stock', $user);

    $this->actingAs($user)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertSee('Negativo');
});

// --- Kardex ---

test('el kardex muestra los movimientos del ítem', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), 500, 'Carga inicial', $user);

    $this->actingAs($user)
        ->get(route('stock.show', ['ingredient', $ingredient->id]))
        ->assertOk()
        ->assertSee('Carga inicial')
        ->assertSee('Ajuste');
});

test('el kardex de un tipo inválido devuelve 404', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get("/stock/producto/{$ingredient->id}")
        ->assertNotFound();
});

// --- Acciones HTTP ---

test('owner registra un ajuste por HTTP', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['ingredient', $ingredient->id]), [
            'quantity' => 250,
            'reason' => 'Carga inicial',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(250.0);
});

test('admin registra una merma de un descartable por HTTP', function () {
    [$user, $tenant] = stockCrudUser(TenantUserRole::Admin);
    $packaging = Packaging::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('stock.wastes.store', ['packaging', $packaging->id]), [
            'quantity' => 5,
            'reason' => 'Cajas mojadas',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect((float) $packaging->stockLevels()->first()->quantity)->toBe(-5.0);
});

test('el recuento por HTTP persiste el delta', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), 1000, 'Carga inicial', $user);

    $this->actingAs($user)
        ->post(route('stock.counts.store', ['ingredient', $ingredient->id]), [
            'counted_quantity' => 900,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(900.0);
});

test('el mínimo se setea y se limpia por HTTP', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    $this->actingAs($user)
        ->patch(route('stock.min.update', ['ingredient', $ingredient->id]), ['min_quantity' => 500])
        ->assertRedirect();

    expect((float) $ingredient->stockLevels()->first()->min_quantity)->toBe(500.0);

    $this->actingAs($user)
        ->patch(route('stock.min.update', ['ingredient', $ingredient->id]), ['min_quantity' => null])
        ->assertRedirect();

    expect($ingredient->stockLevels()->first()->fresh()->min_quantity)->toBeNull();
});

test('el ajuste sin motivo falla la validación', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['ingredient', $ingredient->id]), [
            'quantity' => 10,
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    expect($ingredient->stockMovements()->count())->toBe(0);
});

// --- Permisos ---

test('viewer no puede registrar movimientos de stock', function () {
    [$user, $tenant] = stockCrudUser(TenantUserRole::Viewer);
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['ingredient', $ingredient->id]), [
            'quantity' => 10,
            'reason' => 'Intento',
        ])
        ->assertForbidden();

    expect($ingredient->stockMovements()->count())->toBe(0);
});

// --- Aislamiento entre tenants ---

test('no se puede ver ni operar el stock de un ítem de otro tenant', function () {
    [$userA] = stockCrudUser();
    [, $tenantB] = stockCrudUser();
    $foreignIngredient = Ingredient::factory()->for($tenantB)->create(['name' => 'Harina ajena']);

    $this->actingAs($userA)
        ->get(route('stock.show', ['ingredient', $foreignIngredient->id]))
        ->assertNotFound();

    $this->actingAs($userA)
        ->post(route('stock.adjustments.store', ['ingredient', $foreignIngredient->id]), [
            'quantity' => 10,
            'reason' => 'Cruce',
        ])
        ->assertNotFound();

    expect($foreignIngredient->stockMovements()->count())->toBe(0);
});

// --- Edición inline desde los catálogos ---

test('la edición inline de stock registra un recuento con el delta', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), 1000, 'Carga inicial', $user);

    $this->actingAs($user)
        ->patchJson(route('stock.level.update', ['ingredient', $ingredient->id]), [
            'counted_quantity' => 850,
        ])
        ->assertOk()
        ->assertJson(['quantity' => 850.0]);

    $movement = $ingredient->stockMovements()->latest('id')->first();
    expect($movement->type)->toBe(StockMovementType::Count)
        ->and((float) $movement->quantity)->toBe(-150.0)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(850.0);
});

test('la edición inline de stock funciona para descartables', function () {
    [$user, $tenant] = stockCrudUser(TenantUserRole::Admin);
    $packaging = Packaging::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('stock.level.update', ['packaging', $packaging->id]), [
            'counted_quantity' => 40,
        ])
        ->assertOk()
        ->assertJson(['quantity' => 40.0]);

    expect((float) $packaging->stockLevels()->first()->quantity)->toBe(40.0);
});

test('la edición inline sin diferencia no crea movimientos', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), 500, 'Carga inicial', $user);

    $this->actingAs($user)
        ->patchJson(route('stock.level.update', ['ingredient', $ingredient->id]), [
            'counted_quantity' => 500,
        ])
        ->assertOk()
        ->assertJson(['quantity' => 500.0]);

    expect($ingredient->stockMovements()->count())->toBe(1);
});

test('la edición inline rechaza cantidades negativas', function () {
    [$user, $tenant] = stockCrudUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('stock.level.update', ['ingredient', $ingredient->id]), [
            'counted_quantity' => -10,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('counted_quantity');

    expect($ingredient->stockMovements()->count())->toBe(0);
});

test('viewer no puede editar stock inline', function () {
    [$user, $tenant] = stockCrudUser(TenantUserRole::Viewer);
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('stock.level.update', ['ingredient', $ingredient->id]), [
            'counted_quantity' => 10,
        ])
        ->assertForbidden();

    expect($ingredient->stockMovements()->count())->toBe(0);
});

test('no se puede editar inline el stock de un ítem de otro tenant', function () {
    [$userA] = stockCrudUser();
    [, $tenantB] = stockCrudUser();
    $foreignIngredient = Ingredient::factory()->for($tenantB)->create();

    $this->actingAs($userA)
        ->patchJson(route('stock.level.update', ['ingredient', $foreignIngredient->id]), [
            'counted_quantity' => 10,
        ])
        ->assertNotFound();

    expect($foreignIngredient->stockMovements()->count())->toBe(0);
});

test('el listado no muestra ítems de otro tenant', function () {
    [$userA, $tenantA] = stockCrudUser();
    [, $tenantB] = stockCrudUser();

    Ingredient::factory()->for($tenantA)->create(['name' => 'Harina propia']);
    Ingredient::factory()->for($tenantB)->create(['name' => 'Harina ajena']);

    $this->actingAs($userA)
        ->get(route('stock.index'))
        ->assertOk()
        ->assertSee('Harina propia')
        ->assertDontSee('Harina ajena');
});

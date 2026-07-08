<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\StockService;
use Symfony\Component\HttpKernel\Exception\HttpException;

function stockTenantUser(TenantUserRole $role = TenantUserRole::Owner): array
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

function stockService(): StockService
{
    return app(StockService::class);
}

// --- Location default ---

test('defaultLocation crea Casa Central cuando el tenant no tiene sucursales', function () {
    [, $tenant] = stockTenantUser();

    expect($tenant->locations()->count())->toBe(0);

    $location = $tenant->defaultLocation();

    expect($location->name)->toBe('Casa Central')
        ->and($location->is_default)->toBeTrue()
        ->and($tenant->locations()->count())->toBe(1);

    // Llamarla de nuevo no duplica.
    expect($tenant->defaultLocation()->id)->toBe($location->id)
        ->and($tenant->locations()->count())->toBe(1);
});

test('defaultLocation reutiliza una sucursal existente aunque no tenga el flag default', function () {
    [, $tenant] = stockTenantUser();
    $existing = $tenant->locations()->create(['name' => 'Depósito', 'is_default' => false, 'active' => true]);

    expect($tenant->defaultLocation()->id)->toBe($existing->id)
        ->and($tenant->locations()->count())->toBe(1);
});

// --- Movimientos básicos ---

test('una entrada suma stock y crea la fila de cache', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0.5]);
    $location = $tenant->defaultLocation();

    $movement = stockService()->registerAdjustment($ingredient, $location, 1000, 'Carga inicial', $user);

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and((float) $movement->quantity)->toBe(1000.0)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(1000.0);
});

test('una salida resta stock', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0.5]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, 1000, 'Carga inicial', $user);
    stockService()->registerAdjustment($ingredient, $location, -300, 'Consumo interno', $user);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(700.0);
});

test('el stock puede quedar negativo sin bloquear la salida y marca alerta', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, -50, 'Salida sin stock', $user);

    $level = $ingredient->stockLevels()->first();

    expect((float) $level->quantity)->toBe(-50.0)
        ->and($level->hasAlert())->toBeTrue();
});

test('la alerta se activa cuando el stock queda en o bajo el mínimo', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, 100, 'Carga inicial', $user);
    stockService()->setMinQuantity($ingredient, $location, 100);

    expect($ingredient->stockLevels()->first()->hasAlert())->toBeTrue();

    stockService()->setMinQuantity($ingredient, $location, 50);

    expect($ingredient->stockLevels()->first()->fresh()->hasAlert())->toBeFalse();
});

test('el ajuste sin motivo aborta con 422', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();
    $location = $tenant->defaultLocation();

    stockService()->registerMovement($ingredient, $location, StockMovementType::Adjustment, 10, 0, null, $user);
})->throws(HttpException::class);

test('la merma registra cantidad negativa valuada al costo actual del ítem', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0.75]);
    $location = $tenant->defaultLocation();

    $movement = stockService()->registerWaste($ingredient, $location, 200, 'Se quemó la tanda', $user);

    expect((float) $movement->quantity)->toBe(-200.0)
        ->and((float) $movement->unit_cost)->toBe(0.75)
        ->and($movement->type)->toBe(StockMovementType::Waste)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(-200.0);
});

// --- Recuento ---

test('el recuento registra el delta entre lo contado y el stock actual', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, 1000, 'Carga inicial', $user);

    $movement = stockService()->applyCount($ingredient, $location, 850, $user);

    expect($movement->type)->toBe(StockMovementType::Count)
        ->and((float) $movement->quantity)->toBe(-150.0)
        ->and($movement->reason)->toContain('Recuento físico')
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(850.0);
});

test('el recuento con delta positivo suma stock', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    $movement = stockService()->applyCount($ingredient, $location, 500, $user);

    expect((float) $movement->quantity)->toBe(500.0)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(500.0);
});

test('el recuento sin diferencia no genera movimiento', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, 300, 'Carga inicial', $user);

    expect(stockService()->applyCount($ingredient, $location, 300, $user))->toBeNull()
        ->and($ingredient->stockMovements()->count())->toBe(1);
});

// --- Ledger inmutable ---

test('el ledger no permite actualizar movimientos', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();
    $movement = stockService()->registerAdjustment($ingredient, $tenant->defaultLocation(), 10, 'Carga', $user);

    $movement->update(['quantity' => 999]);
})->throws(LogicException::class);

test('el ledger no permite borrar movimientos', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();
    $movement = stockService()->registerAdjustment($ingredient, $tenant->defaultLocation(), 10, 'Carga', $user);

    $movement->delete();
})->throws(LogicException::class);

// --- Packaging ---

test('los descartables se manejan en stock igual que los insumos', function () {
    [$user, $tenant] = stockTenantUser();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 12.5]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($packaging, $location, 100, 'Carga inicial', $user);
    stockService()->registerWaste($packaging, $location, 10, 'Cajas rotas', $user);
    $count = stockService()->applyCount($packaging, $location, 85, $user);

    $level = $packaging->stockLevels()->first();

    expect($level->stockable_type)->toBe('packaging')
        ->and((float) $level->quantity)->toBe(85.0)
        ->and((float) $count->quantity)->toBe(-5.0)
        ->and($packaging->stockMovements()->count())->toBe(3);
});

test('insumo y descartable con el mismo id no comparten stock', function () {
    [$user, $tenant] = stockTenantUser();
    $ingredient = Ingredient::factory()->for($tenant)->create();
    $packaging = Packaging::factory()->for($tenant)->create();
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($ingredient, $location, 100, 'Carga', $user);

    expect($packaging->stockLevels()->count())->toBe(0)
        ->and($packaging->stockMovements()->count())->toBe(0);
});

// --- Aislamiento entre tenants ---

test('los movimientos no se cruzan entre tenants', function () {
    [$userA, $tenantA] = stockTenantUser();
    [$userB, $tenantB] = stockTenantUser();

    $ingredientA = Ingredient::factory()->for($tenantA)->create();
    $ingredientB = Ingredient::factory()->for($tenantB)->create();

    stockService()->registerAdjustment($ingredientA, $tenantA->defaultLocation(), 100, 'Carga A', $userA);
    stockService()->registerAdjustment($ingredientB, $tenantB->defaultLocation(), 40, 'Carga B', $userB);

    expect(StockMovement::where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(StockMovement::where('tenant_id', $tenantB->id)->count())->toBe(1)
        ->and((float) $ingredientA->stockLevels()->first()->quantity)->toBe(100.0)
        ->and((float) $ingredientB->stockLevels()->first()->quantity)->toBe(40.0);
});

test('no se puede mover stock en una sucursal de otro tenant', function () {
    [$userA, $tenantA] = stockTenantUser();
    [, $tenantB] = stockTenantUser();

    $ingredientA = Ingredient::factory()->for($tenantA)->create();

    stockService()->registerAdjustment($ingredientA, $tenantB->defaultLocation(), 100, 'Cruce', $userA);
})->throws(HttpException::class);

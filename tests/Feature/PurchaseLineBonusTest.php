<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\PurchaseLineRecorder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Renglones sin cargo: obsequios, promociones y muestras de la distribuidora.
 * Entran al stock pero no imputan costo al catálogo — la razón por la que antes
 * el cliente los dejaba sin asociar y perdía la mercadería de vista.
 */
function bonusOwner(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function bonusPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-09-01']);
}

function bonusLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'HARINA 000 OBSEQUIO',
        'quantity_purchased' => 2,
        'purchase_unit' => 'kg',
        'unit_price' => 0,
        'subtotal' => 0,
        'is_bonus' => true,
    ], $overrides));
}

function bonusRecorder(): PurchaseLineRecorder
{
    return app(PurchaseLineRecorder::class);
}

// --- Lo esencial: entra al stock, no toca el costo ---

test('un renglón sin cargo suma stock sin tocar el costo del insumo', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Gramo,
        'cost_per_unit' => 1.5,
    ]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    bonusRecorder()->apply($line);

    $movement = $ingredient->stockMovements()->first();

    expect($movement->type)->toBe(StockMovementType::Bonus)
        ->and((float) $movement->quantity)->toBe(2000.0)          // 2 kg → 2000 gr
        ->and((float) $movement->unit_cost)->toBe(1.5)            // valuado al costo vigente, no a $0
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(1.5)
        ->and($line->fresh()->isApplied())->toBeTrue();
});

test('un renglón sin cargo no deja rastro en el historial de precios', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 1.5]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    bonusRecorder()->apply($line);

    expect($ingredient->priceLogs()->count())->toBe(0);
});

test('un renglón sin cargo no pisa el último costo de compra del cache de stock', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0]);
    $purchase = bonusPurchaseFor($tenant);

    // Compra real primero: deja el último costo en $1/gr.
    $paid = $purchase->lines()->create([
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 1,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'subtotal' => 1000,
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);
    bonusRecorder()->apply($paid);

    $gift = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);
    bonusRecorder()->apply($gift);

    $level = $ingredient->stockLevels()->first();

    expect((float) $level->unit_cost)->toBe(1.0)
        ->and((float) $level->quantity)->toBe(3000.0); // 1000 comprados + 2000 de obsequio
});

test('un renglón sin cargo no propaga costos a las recetas que usan el insumo', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo,
        'cost_per_unit' => 1000,
    ]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => 'u']);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    bonusRecorder()->apply($line);

    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(1000.0);
});

// --- Unidades incompatibles: el divisor tiene que venir del formulario ---

test('un renglón sin cargo con unidades incompatibles usa el divisor explícito', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo,
        'cost_per_unit' => 800,
    ]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'raw_name' => 'CAJA PROMO SIN CARGO',
        'quantity_purchased' => 3,
        'purchase_unit' => 'u',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    bonusRecorder()->apply($line, pkgQtyOverride: 5.0);

    $movement = $ingredient->stockMovements()->first();

    expect((float) $movement->quantity)->toBe(15.0)      // 3 bultos × 5 kg
        ->and((float) $movement->unit_cost)->toBe(800.0)
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(800.0);
});

test('un renglón sin cargo con unidades incompatibles y sin divisor es rechazado', function () {
    [, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo, 'cost_per_unit' => 800]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'raw_name' => 'CAJA PROMO SIN CARGO',
        'purchase_unit' => 'u',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    expect(fn () => bonusRecorder()->apply($line))
        ->toThrow(HttpException::class);
});

test('un descartable sin cargo con subdivisiones suma las sub-unidades del bulto', function () {
    [, $tenant] = bonusOwner();
    $packaging = Packaging::factory()->for($tenant)->create([
        'cost_per_unit' => 25,
        'subdivisions' => 12,
    ]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'raw_name' => 'BANDEJAS OBSEQUIO',
        'quantity_purchased' => 2,
        'purchase_unit' => 'u',
        'purchaseable_type' => 'packaging',
        'purchaseable_id' => $packaging->id,
    ]);

    bonusRecorder()->apply($line);

    $movement = $packaging->stockMovements()->first();

    expect((float) $movement->quantity)->toBe(24.0)       // 2 bultos × 12 unidades
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and((float) $packaging->fresh()->cost_per_unit)->toBe(25.0)
        ->and($packaging->fresh()->cost_per_package)->toBeNull();
});

// --- Auto-detección por precio cero ---

test('storePending propone como sin cargo únicamente los renglones a precio cero', function () {
    [, $tenant] = bonusOwner();
    $purchase = bonusPurchaseFor($tenant);

    $gift = bonusRecorder()->storePending($purchase, [
        'raw_name' => 'OBSEQUIO',
        'quantity_purchased' => 1,
        'purchase_unit' => 'u',
        'unit_price' => 0,
    ]);

    $paid = bonusRecorder()->storePending($purchase, [
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 1,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
    ]);

    expect($gift->isBonus())->toBeTrue()
        ->and($paid->isBonus())->toBeFalse();
});

test('lo que dice el formulario gana sobre la inferencia por precio', function () {
    [, $tenant] = bonusOwner();
    $purchase = bonusPurchaseFor($tenant);

    $line = bonusRecorder()->storePending($purchase, [
        'raw_name' => 'DESCUENTO 100% APLICADO',
        'quantity_purchased' => 1,
        'purchase_unit' => 'u',
        'unit_price' => 0,
        'is_bonus' => false,
    ]);

    expect($line->isBonus())->toBeFalse();
});

// --- Flujo completo por HTTP ---

test('asociar un renglón marcándolo sin cargo lo imputa como bonificación', function () {
    [$user, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 2]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, ['is_bonus' => false]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
            'is_bonus' => '1',
        ])
        ->assertRedirect();

    $line->refresh();

    expect($line->isBonus())->toBeTrue()
        ->and($line->isApplied())->toBeTrue()
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(2.0)
        ->and($ingredient->stockMovements()->where('type', StockMovementType::Bonus)->count())->toBe(1);
});

test('desasociar un renglón sin cargo revierte su entrada y limpia la marca', function () {
    [$user, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 2]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);
    bonusRecorder()->apply($line);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => ''])
        ->assertRedirect();

    $line->refresh();

    expect($line->isBonus())->toBeFalse()
        ->and($line->isPending())->toBeTrue()
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($ingredient->stockMovements()->count())->toBe(2); // entrada + contramovimiento
});

test('marcar como consumo personal un renglón sin cargo limpia la marca', function () {
    [$user, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 2]);
    $purchase = bonusPurchaseFor($tenant);
    $line = bonusLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);
    bonusRecorder()->apply($line);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => 'excluded'])
        ->assertRedirect();

    $line->refresh();

    expect($line->isBonus())->toBeFalse()
        ->and($line->isExcluded())->toBeTrue()
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0);
});

test('pasar un renglón ya aplicado a sin cargo reemplaza su entrada de stock', function () {
    [$user, $tenant] = bonusOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0]);
    $purchase = bonusPurchaseFor($tenant);
    $line = $purchase->lines()->create([
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 1,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'subtotal' => 1000,
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);
    bonusRecorder()->apply($line);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
            'is_bonus' => '1',
        ])
        ->assertRedirect();

    // Una sola entrada vigente: la de compra fue contramovida al pasar a bonificación.
    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(1000.0)
        ->and($ingredient->stockMovements()->where('type', StockMovementType::Bonus)->whereNull('reverses_movement_id')->count())->toBe(1);
});

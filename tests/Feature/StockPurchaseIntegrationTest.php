<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\PurchaseLineRecorder;
use App\Services\StockService;

function stockPurchaseOwner(): array
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

function stockPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-07']);
}

function stockLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 2,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'subtotal' => 2000,
    ], $overrides));
}

function lineRecorder(): PurchaseLineRecorder
{
    return app(PurchaseLineRecorder::class);
}

// --- Entrada por compra (conversión de unidades) ---

test('aplicar una línea de 2 kg sobre un insumo en gramos genera entrada de 2000 gr con costo por gramo', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);

    $movement = $ingredient->stockMovements()->first();
    $level = $ingredient->stockLevels()->first();

    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and((float) $movement->quantity)->toBe(2000.0)
        ->and((float) $movement->unit_cost)->toBe(1.0) // $1000/kg = $1/gr
        ->and($movement->reference_type)->toBe('purchase_line')
        ->and($movement->reference_id)->toBe($line->id)
        ->and((float) $level->quantity)->toBe(2000.0)
        ->and((float) $level->unit_cost)->toBe(1.0)
        ->and($level->location_id)->toBe($tenant->defaultLocation()->id);
});

test('re-aplicar la misma línea no duplica la entrada (idempotencia)', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);
    lineRecorder()->apply($line->fresh());

    expect($ingredient->stockMovements()->count())->toBe(1)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(2000.0);
});

test('insumo con subdivisiones comprado por unidad entra en sub-unidades', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'subdivisions' => 12,
        'subdivision_label' => 'huevo',
    ]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'MAPLE HUEVOS',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'u',
        'unit_price' => 3600,
        'subtotal' => 7200,
    ]);

    lineRecorder()->apply($line);

    $movement = $ingredient->stockMovements()->first();

    expect((float) $movement->quantity)->toBe(24.0) // 2 maples × 12
        ->and((float) $movement->unit_cost)->toBe(300.0); // $3600 / 12
});

test('compra de descartable con subdivisiones entra en sub-unidades con costo por sub-unidad', function () {
    [, $tenant] = stockPurchaseOwner();
    $packaging = Packaging::factory()->for($tenant)->create(['subdivisions' => 12, 'subdivision_label' => 'caja']);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'CAJAS PIZZA X12',
        'purchaseable_type' => 'packaging',
        'purchaseable_id' => $packaging->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'u',
        'unit_price' => 2400,
        'subtotal' => 4800,
    ]);

    lineRecorder()->apply($line);

    $movement = $packaging->stockMovements()->first();
    $level = $packaging->stockLevels()->first();

    expect($movement->stockable_type)->toBe('packaging')
        ->and((float) $movement->quantity)->toBe(24.0)
        ->and((float) $movement->unit_cost)->toBe(200.0)
        ->and((float) $level->quantity)->toBe(24.0);
});

test('compra de descartable sin subdivisiones entra unidad por unidad', function () {
    [, $tenant] = stockPurchaseOwner();
    $packaging = Packaging::factory()->for($tenant)->create(['subdivisions' => null]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'BOLSAS KRAFT',
        'purchaseable_type' => 'packaging',
        'purchaseable_id' => $packaging->id,
        'quantity_purchased' => 50,
        'purchase_unit' => 'u',
        'unit_price' => 30,
        'subtotal' => 1500,
    ]);

    lineRecorder()->apply($line);

    expect((float) $packaging->stockLevels()->first()->quantity)->toBe(50.0)
        ->and((float) $packaging->stockMovements()->first()->unit_cost)->toBe(30.0);
});

test('línea con unidades incompatibles usa la pista de la descripción para la cantidad', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'HARINA X 25 Kg',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 3,
        'purchase_unit' => 'u',
        'unit_price' => 25000,
        'subtotal' => 75000,
    ]);

    lineRecorder()->apply($line);

    $movement = $ingredient->stockMovements()->first();

    expect((float) $movement->quantity)->toBe(75000.0) // 3 bolsas × 25000 gr
        ->and((float) $movement->unit_cost)->toBe(1.0); // $25000 / 25000 gr
});

test('applyWithCost deriva la cantidad desde el costo explícito', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'BOLSA HARINA SIN PISTA',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'u',
        'unit_price' => 25000,
        'subtotal' => 50000,
    ]);

    // $25000 la bolsa a $1/gr => 25000 gr por bolsa.
    lineRecorder()->applyWithCost($line, 1.0);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(50000.0);
});

test('applyWithCost con costo cero imputa el costo pero no registra stock', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'purchase_unit' => 'u',
    ]);

    lineRecorder()->applyWithCost($line, 0.0);

    expect($line->fresh()->isApplied())->toBeTrue()
        ->and($ingredient->stockMovements()->count())->toBe(0);
});

// --- Corrección de compras ---

test('editar una línea aplicada revierte la entrada anterior y registra la nueva', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);

    lineRecorder()->recompute($line->fresh(), [
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 3,
        'purchase_unit' => 'kg',
        'unit_price' => 1200,
    ]);

    $movements = $ingredient->stockMovements()->orderBy('id')->get();

    expect($movements)->toHaveCount(3) // entrada, contramovimiento, nueva entrada
        ->and((float) $movements[0]->quantity)->toBe(2000.0)
        ->and((float) $movements[1]->quantity)->toBe(-2000.0)
        ->and($movements[1]->reverses_movement_id)->toBe($movements[0]->id)
        ->and((float) $movements[1]->unit_cost)->toBe((float) $movements[0]->unit_cost)
        ->and((float) $movements[2]->quantity)->toBe(3000.0)
        ->and((float) $ingredient->stockLevels()->first()->quantity)->toBe(3000.0);
});

test('el contramovimiento revierte exactamente y deja el neto en cero', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);
    app(StockService::class)->reversePurchaseLineEntry($line, $user);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($ingredient->stockMovements()->count())->toBe(2);
});

test('borrar una línea aplicada revierte su stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);

    $this->actingAs($user)
        ->delete(route('purchases.lines.destroy', [$purchase, $line]))
        ->assertRedirect();

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and(PurchaseLine::find($line->id))->toBeNull()
        ->and($ingredient->stockMovements()->count())->toBe(2); // el ledger conserva entrada y reversa
});

test('borrar una compra revierte el stock de todas sus líneas aplicadas', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $packaging = Packaging::factory()->for($tenant)->create(['subdivisions' => null]);
    $purchase = stockPurchaseFor($tenant);

    $ingredientLine = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    $packagingLine = stockLineFor($purchase, [
        'raw_name' => 'BOLSAS',
        'purchaseable_type' => 'packaging',
        'purchaseable_id' => $packaging->id,
        'quantity_purchased' => 50,
        'purchase_unit' => 'u',
        'unit_price' => 30,
        'subtotal' => 1500,
    ]);
    $pendingLine = stockLineFor($purchase, ['raw_name' => 'PENDIENTE']);

    lineRecorder()->apply($ingredientLine);
    lineRecorder()->apply($packagingLine);

    $this->actingAs($user)
        ->delete(route('purchases.destroy', $purchase))
        ->assertRedirect(route('purchases.index'));

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and((float) $packaging->stockLevels()->first()->quantity)->toBe(0.0)
        ->and(Purchase::find($purchase->id))->toBeNull();
});

test('marcar una línea aplicada como sin asociar revierte su stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => ''])
        ->assertRedirect();

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($line->fresh()->isMatched())->toBeFalse();
});

test('marcar una línea aplicada como consumo personal revierte su stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => 'excluded'])
        ->assertRedirect();

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($line->fresh()->isExcluded())->toBeTrue()
        ->and($line->fresh()->isMatched())->toBeFalse()
        // Contramovimiento, no borrado: el ledger es append-only.
        ->and($ingredient->stockMovements()->count())->toBe(2);
});

test('marcar una línea pendiente como consumo personal no genera movimiento de stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 7]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => 'excluded'])
        ->assertRedirect();

    expect(StockMovement::count())->toBe(0)
        ->and($ingredient->stockLevels()->count())->toBe(0)
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(7.0);
});

test('re-asociar la línea a otro insumo mueve el stock del viejo al nuevo', function () {
    [, $tenant] = stockPurchaseOwner();
    $original = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $correct = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $original->id]);

    lineRecorder()->apply($line);

    $line->update(['purchaseable_type' => 'ingredient', 'purchaseable_id' => $correct->id]);
    lineRecorder()->apply($line->fresh());

    expect((float) $original->stockLevels()->first()->quantity)->toBe(0.0)
        ->and((float) $correct->stockLevels()->first()->quantity)->toBe(2000.0);
});

test('las líneas de otro tenant no afectan el stock propio', function () {
    [, $tenantA] = stockPurchaseOwner();
    [, $tenantB] = stockPurchaseOwner();

    $ingredientA = Ingredient::factory()->for($tenantA)->create(['unit' => Unit::Gramo]);
    $purchaseB = stockPurchaseFor($tenantB);
    $ingredientB = Ingredient::factory()->for($tenantB)->create(['unit' => Unit::Gramo]);
    $lineB = stockLineFor($purchaseB, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredientB->id]);

    lineRecorder()->apply($lineB);

    expect(StockMovement::where('tenant_id', $tenantA->id)->count())->toBe(0)
        ->and($ingredientA->stockLevels()->count())->toBe(0)
        ->and(StockMovement::where('tenant_id', $tenantB->id)->count())->toBe(1);
});

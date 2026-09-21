<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\CreditNote;
use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\CreditNoteLineRecorder;
use App\Services\PurchaseLineRecorder;
use Symfony\Component\HttpKernel\Exception\HttpException;

function creditNoteStockOwner(): array
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

function creditNoteStockPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-07']);
}

function creditNoteStockLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 4,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'subtotal' => 4000,
    ], $overrides));
}

function creditNoteFor(Tenant $tenant, Purchase $purchase, array $overrides = []): CreditNote
{
    return $tenant->creditNotes()->create(array_merge([
        'supplier_id' => $purchase->supplier_id,
        'purchase_id' => $purchase->id,
        'note_date' => '2026-07-10',
    ], $overrides));
}

function purchaseLineRecorder(): PurchaseLineRecorder
{
    return app(PurchaseLineRecorder::class);
}

function creditNoteLineRecorder(): CreditNoteLineRecorder
{
    return app(CreditNoteLineRecorder::class);
}

test('devolución total deja el neto de stock en cero', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['quantity_purchased' => 2, 'subtotal' => 2000, 'purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line);

    $note = creditNoteFor($tenant, $purchase);
    $cnLine = creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 2,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($cnLine->isStockApplied())->toBeTrue()
        ->and($ingredient->stockMovements()->count())->toBe(2);

    $exit = $ingredient->stockMovements()->where('type', StockMovementType::Return->value)->first();
    expect((float) $exit->quantity)->toBe(-2000.0)
        ->and($exit->reference_type)->toBe('credit_note_line')
        ->and($exit->reference_id)->toBe($cnLine->id);
});

test('devolución parcial con conversión de unidades descuenta la proporción exacta', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line); // 4kg -> 4000gr

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1, // 1kg de los 4kg comprados
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(3000.0);
});

test('devolución sobre insumo con subdivisiones descuenta la proporción en sub-unidades', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'subdivisions' => 12,
        'subdivision_label' => 'huevo',
    ]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, [
        'raw_name' => 'MAPLE HUEVOS',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'u',
        'unit_price' => 3600,
        'subtotal' => 7200,
    ]);
    purchaseLineRecorder()->apply($line); // 2 maples -> 24 huevos

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1, // 1 de los 2 maples
        'unit' => 'u',
        'unit_price' => 3600,
        'affects_stock' => true,
    ]);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(12.0); // 24 - 12
});

test('un renglón que no descuenta stock no genera movimiento', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line);

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => false,
    ]);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(4000.0)
        ->and($ingredient->stockMovements()->count())->toBe(1);
});

test('un renglón libre sin renglón de compra no genera movimiento aunque pida descontar stock', function () {
    [, $tenant] = creditNoteStockOwner();
    $purchase = creditNoteStockPurchaseFor($tenant);
    $note = creditNoteFor($tenant, $purchase);

    $cnLine = creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => null,
        'description' => 'Rotura de insumos ya ajustada por recuento',
        'quantity' => 1,
        'unit' => 'u',
        'unit_price' => 5000,
        'affects_stock' => true,
    ]);

    expect($cnLine->isStockApplied())->toBeFalse()
        ->and($cnLine->affectsStock())->toBeFalse();
});

test('la nota de crédito no toca el costo del insumo ni el historial de precios', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line);

    $costBefore = (float) $ingredient->fresh()->cost_per_unit;
    $priceLogCountBefore = $ingredient->priceLogs()->count();
    $unitCostBefore = (float) $ingredient->stockLevels()->first()->unit_cost;

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 4,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);

    expect((float) $ingredient->fresh()->cost_per_unit)->toBe($costBefore)
        ->and($ingredient->priceLogs()->count())->toBe($priceLogCountBefore)
        ->and((float) $ingredient->stockLevels()->first()->unit_cost)->toBe($unitCostBefore);
});

test('editar la cantidad devuelta revierte y re-registra la salida', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line); // 4000gr

    $note = creditNoteFor($tenant, $purchase);
    $cnLine = creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);
    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(3000.0);

    creditNoteLineRecorder()->recompute($cnLine->fresh(), [
        'purchase_line_id' => $line->id,
        'quantity' => 2,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(2000.0)
        ->and($ingredient->stockMovements()->count())->toBe(4); // compra + 1° salida + reversa de la 1° salida + 2° salida
});

test('borrar la nota de crédito revierte el stock de sus renglones aplicados', function () {
    [$user, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line);

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);
    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(3000.0);

    $this->actingAs($user)->delete(route('credit-notes.destroy', $note))->assertRedirect(route('credit-notes.index'));

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(4000.0);
});

test('devolver más cantidad de la comprada aborta con 422', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line);

    $note = creditNoteFor($tenant, $purchase);

    expect(fn () => creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 5, // sólo se compraron 4kg
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]))->toThrow(HttpException::class);
});

test('devolver sobre un renglón de compra todavía no aplicado aborta con 422', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    // Sin apply(): el renglón queda pendiente, nunca imputó costo ni stock.

    $note = creditNoteFor($tenant, $purchase);

    expect(fn () => creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]))->toThrow(HttpException::class);
});

test('editar el renglón de compra después de una devolución no duplica stock', function () {
    [, $tenant] = creditNoteStockOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = creditNoteStockPurchaseFor($tenant);
    $line = creditNoteStockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);
    purchaseLineRecorder()->apply($line); // 4kg -> 4000gr

    $note = creditNoteFor($tenant, $purchase);
    creditNoteLineRecorder()->storeLine($note, [
        'purchase_line_id' => $line->id,
        'quantity' => 1,
        'unit' => 'kg',
        'unit_price' => 1000,
        'affects_stock' => true,
    ]);
    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(3000.0);

    // Se corrige la compra original: en realidad eran 5kg, no 4kg.
    $line->setRelation('purchase', $purchase->loadMissing('tenant'));
    purchaseLineRecorder()->recompute($line, [
        'raw_name' => $line->raw_name,
        'quantity_purchased' => 5,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'iva_rate' => (float) $line->iva_rate,
    ]);

    // 3000 (después de la devolución) - 4000 (reversa de la entrada vieja) + 5000 (entrada nueva) = 4000
    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(4000.0)
        ->and($ingredient->stockMovements()->count())->toBe(4);
});

<?php

use App\Models\Ingredient;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseLineRecorder;

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @return array{0: User, 1: Tenant, 2: Purchase}
 */
function purchaseForDiscounts(array $lines = [['subtotal' => 1000, 'iva_rate' => 0.21]]): array
{
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-13']);

    foreach ($lines as $line) {
        PurchaseLine::factory()->for($purchase)->create($line);
    }

    return [$user, $tenant, $purchase];
}

test('un descuento global porcentual baja el neto y su IVA se prorratea', function () {
    [$user, , $purchase] = purchaseForDiscounts([
        ['subtotal' => 1000, 'iva_rate' => 0.21],
        ['subtotal' => 1000, 'iva_rate' => 0],
    ]);

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'description' => 'Bonificación', 'kind' => 'percent', 'rate' => 10,
    ])->assertRedirect();

    $discount = $purchase->discounts()->firstOrFail();

    expect((float) $discount->amount)->toBe(200.0)
        ->and((float) $discount->iva_amount)->toBe(21.0) // 200 × (210 / 2000)
        ->and($discount->isGlobal())->toBeTrue()
        ->and($purchase->fresh()->discountsTotal())->toBe(200.0);
});

test('un descuento por monto es fijo y no se recalcula al cambiar los renglones', function () {
    [$user, , $purchase] = purchaseForDiscounts();

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'amount', 'amount' => 150,
    ]);

    $line = $purchase->lines()->firstOrFail();
    $this->actingAs($user)->patchJson(route('purchases.lines.price.update', [$purchase, $line]), ['unit_price' => 5000])
        ->assertOk();

    expect((float) $purchase->discounts()->firstOrFail()->amount)->toBe(150.0);
});

test('un descuento porcentual se recalcula al cambiar el precio de un renglón', function () {
    [$user, , $purchase] = purchaseForDiscounts([['quantity_purchased' => 1, 'unit_price' => 1000, 'subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'percent', 'rate' => 10,
    ]);

    $line = $purchase->lines()->firstOrFail();
    $response = $this->actingAs($user)->patchJson(route('purchases.lines.price.update', [$purchase, $line]), ['unit_price' => 2000])
        ->assertOk();

    expect((float) $purchase->discounts()->firstOrFail()->amount)->toBe(200.0)
        ->and($response->json('total_discount'))->toEqual(200)
        ->and($response->json('grand_total'))->toEqual(2000 - 200 + 420 - 42);
});

test('un descuento atado a un renglón usa la base y el IVA de ese renglón', function () {
    [$user, , $purchase] = purchaseForDiscounts([
        ['subtotal' => 1000, 'iva_rate' => 0.21],
        ['subtotal' => 400, 'iva_rate' => 0.105],
    ]);
    $second = $purchase->lines()->orderBy('id')->get()[1];

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'percent', 'rate' => 50, 'purchase_line_id' => $second->id,
    ])->assertRedirect();

    $discount = $purchase->discounts()->firstOrFail();

    expect((float) $discount->amount)->toBe(200.0)
        ->and((float) $discount->iva_amount)->toBe(21.0)
        ->and($discount->purchase_line_id)->toBe($second->id);
});

test('no se puede atar un descuento a un renglón de otra compra', function () {
    [$user, , $purchase] = purchaseForDiscounts();
    [, , $other] = purchaseForDiscounts();
    $foreignLine = $other->lines()->firstOrFail();

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'amount', 'amount' => 10, 'purchase_line_id' => $foreignLine->id,
    ])->assertSessionHasErrors('purchase_line_id');

    expect($purchase->discounts()->count())->toBe(0);
});

test('el porcentaje es obligatorio para un descuento porcentual y el importe para uno por monto', function () {
    [$user, , $purchase] = purchaseForDiscounts();

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), ['kind' => 'percent'])
        ->assertSessionHasErrors('rate');
    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), ['kind' => 'amount'])
        ->assertSessionHasErrors('amount');
});

test('borrar el renglón destino borra su descuento', function () {
    [$user, , $purchase] = purchaseForDiscounts([
        ['subtotal' => 1000, 'iva_rate' => 0.21],
        ['subtotal' => 400, 'iva_rate' => 0.21],
    ]);
    $second = $purchase->lines()->orderBy('id')->get()[1];

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'amount', 'amount' => 40, 'purchase_line_id' => $second->id,
    ]);

    $this->actingAs($user)->delete(route('purchases.lines.destroy', [$purchase, $second]))->assertRedirect();

    expect($purchase->discounts()->count())->toBe(0);
});

test('las percepciones automáticas se calculan sobre la base ya descontada', function () {
    [$user, , $purchase] = purchaseForDiscounts([['subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'percent', 'rate' => 10,
    ]);
    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc. IVA', 'rate' => 3,
    ]);

    expect($purchase->fresh()->taxedNet())->toBe(900.0)
        ->and((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(27.0);
});

test('editar y eliminar un descuento recalcula las percepciones automáticas', function () {
    [$user, , $purchase] = purchaseForDiscounts([['subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc. IVA', 'rate' => 3,
    ]);
    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), ['kind' => 'amount', 'amount' => 100]);
    $discount = $purchase->discounts()->firstOrFail();

    expect((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(27.0);

    $this->actingAs($user)->delete(route('purchases.discounts.destroy', [$purchase, $discount]))->assertRedirect();

    expect((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(30.0);
});

test('un descuento no cambia el costo del insumo al imputar el renglón', function () {
    [$user, $tenant, $purchase] = purchaseForDiscounts([]);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 1]);
    $line = PurchaseLine::factory()->for($purchase)->create([
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 10,
        'purchase_unit' => 'kg',
        'unit_price' => 100,
        'subtotal' => 1000,
        'iva_rate' => 0.21,
    ]);

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'percent', 'rate' => 20,
    ]);

    app(PurchaseLineRecorder::class)->apply($line->load('purchase'));

    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(100.0);
});

test('un descuento de otro negocio responde 404', function () {
    [$user, , $purchase] = purchaseForDiscounts();
    [, , $foreign] = purchaseForDiscounts();
    $foreignDiscount = $foreign->discounts()->create([
        'description' => 'x', 'kind' => 'amount', 'amount' => 10, 'iva_amount' => 0,
    ]);

    $this->actingAs($user)->delete(route('purchases.discounts.destroy', [$foreign, $foreignDiscount]))
        ->assertNotFound();
});

test('el índice resta los descuentos del total', function () {
    [$user, , $purchase] = purchaseForDiscounts([['subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.discounts.store', $purchase), [
        'kind' => 'amount', 'amount' => 100,
    ]);

    // 1000 + 210 de IVA − (100 + 21 de IVA) = 1089
    $this->actingAs($user)->get(route('purchases.index'))->assertOk()->assertSee('1.089,00');
});

test('guardar el escaneo crea descuentos globales y atados a un renglón importado', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('purchases.scan.store'), [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'lines' => [
            0 => ['include' => '1', 'raw_name' => 'HARINA', 'quantity_purchased' => '10', 'purchase_unit' => 'kg', 'unit_price' => '100', 'iva_rate' => '0.21'],
            1 => ['include' => '1', 'raw_name' => 'AZUCAR', 'quantity_purchased' => '10', 'purchase_unit' => 'kg', 'unit_price' => '50', 'iva_rate' => '0.21'],
        ],
        'discounts' => [
            ['description' => 'Bonif. harina', 'kind' => 'amount', 'amount' => '100', 'line_index' => '0'],
            ['description' => 'Desc. pie', 'kind' => 'percent', 'rate' => '10', 'line_index' => ''],
            ['description' => 'Vacío', 'kind' => 'amount', 'amount' => ''],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $purchase = $tenant->purchases()->firstOrFail();
    $discounts = $purchase->discounts()->orderBy('id')->get();
    $harina = $purchase->lines()->where('raw_name', 'HARINA')->firstOrFail();

    expect($discounts)->toHaveCount(2)
        ->and($discounts[0]->purchase_line_id)->toBe($harina->id)
        ->and((float) $discounts[0]->amount)->toBe(100.0)
        ->and($discounts[1]->purchase_line_id)->toBeNull()
        ->and((float) $discounts[1]->amount)->toBe(150.0); // 10% de (1000 + 500)
});

test('un descuento cuyo renglón destino no se importó queda como descuento de toda la factura', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('purchases.scan.store'), [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'lines' => [
            0 => ['raw_name' => 'HARINA', 'quantity_purchased' => '10', 'purchase_unit' => 'kg', 'unit_price' => '100'],
            1 => ['include' => '1', 'raw_name' => 'AZUCAR', 'quantity_purchased' => '10', 'purchase_unit' => 'kg', 'unit_price' => '50', 'iva_rate' => '0.21'],
        ],
        'discounts' => [
            ['description' => 'Bonif. harina', 'kind' => 'amount', 'amount' => '40', 'line_index' => '0'],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($tenant->purchases()->firstOrFail()->discounts()->firstOrFail()->purchase_line_id)->toBeNull();
});

test('un renglón de descuento se convierte en descuento global y revierte su stock', function () {
    [$user, $tenant, $purchase] = purchaseForDiscounts([['subtotal' => 1000, 'iva_rate' => 0.21]]);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 1]);
    $bonif = PurchaseLine::factory()->for($purchase)->create([
        'raw_name' => 'BONIFICACION 5%',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'kg',
        'unit_price' => 25,
        'subtotal' => 50,
        'iva_rate' => 0.21,
    ]);
    app(PurchaseLineRecorder::class)->apply($bonif->load('purchase'));

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $bonif]), ['match' => 'discount'])
        ->assertRedirect();

    $discount = $purchase->discounts()->firstOrFail();

    expect(PurchaseLine::find($bonif->id))->toBeNull()
        ->and($discount->description)->toBe('BONIFICACION 5%')
        ->and((float) $discount->amount)->toBe(50.0)
        ->and($discount->isGlobal())->toBeTrue()
        ->and((float) StockLevel::query()->where('stockable_id', $ingredient->id)->sum('quantity'))->toBe(0.0);
});

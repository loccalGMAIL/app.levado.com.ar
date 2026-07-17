<?php

use App\Models\Purchase;

/**
 * El giro se resuelve en el navegador: el backend recibe la imagen ya rotada y
 * no sabe nada de esto. Lo que se puede fijar del lado del servidor es que los
 * controles se rendericen en los 5 lugares donde se captura un comprobante —
 * el JS vive en un helper compartido, así que un componente roto los rompe a
 * todos a la vez.
 */
test('el alta de gastos ofrece girar el comprobante', function () {
    [$user] = ownerForVariableExpense();

    $this->actingAs($user)
        ->get(route('variable-expenses.index'))
        ->assertOk()
        ->assertSee('rotateReceipt(-1)', escape: false)
        ->assertSee('rotateReceipt(1)', escape: false)
        ->assertSee('Girar a la izquierda')
        ->assertSee('Girar a la derecha');
});

test('la pantalla de escaneo de facturas ofrece girar la imagen', function () {
    [$user] = ownerForScan();

    $this->actingAs($user)
        ->get(route('purchases.scan.create'))
        ->assertOk()
        ->assertSee('rotateInvoice(-1)', escape: false)
        ->assertSee('rotateInvoice(1)', escape: false);
});

test('el alta manual de compras ofrece girar el comprobante', function () {
    [$user] = ownerForScan();

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('rotateInvoice(-1)', escape: false)
        ->assertSee('rotateInvoice(1)', escape: false);
});

test('la edición de una compra ofrece girar el comprobante', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = $tenant->suppliers()->create(['name' => 'Molino', 'active' => true]);
    $purchase = Purchase::create([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
    ]);

    $this->actingAs($user)
        ->get(route('purchases.show', $purchase))
        ->assertOk()
        ->assertSee('rotateInvoice(-1)', escape: false)
        ->assertSee('rotateInvoice(1)', escape: false);
});

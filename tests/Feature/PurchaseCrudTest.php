<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function ownerForPurchaseCrud(): array
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

// --- Proveedor dado de baja ---

test('el detalle de una compra con proveedor inactivo ofrece su opción marcada', function () {
    // El select de edición es `required`: si listara sólo activos, el proveedor dado de baja
    // no matchearía, el select caería en la opción vacía y el navegador bloquearía el
    // guardado de cualquier otro campo hasta reasignarle proveedor a la compra.
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Baja', 'active' => false]);
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
    ]);

    // Mirar el select de edición puntualmente: el nombre del proveedor también se renderiza
    // en la cabecera de la compra, así que un assertSee suelto pasaría aunque el select
    // estuviera roto. Y sin la opción `selected`, el `required` bloquearía el guardado.
    $html = $this->actingAs($user)->get(route('purchases.show', $purchase))->assertOk()->getContent();
    $editSelect = str($html)->after('id="edit_purchase_supplier"')->before('</select>')->toString();

    expect($editSelect)->toContain('Proveedor Baja')
        ->and($editSelect)->toContain('(inactivo)')
        ->and($editSelect)->toContain('selected');
});

test('editar una compra con proveedor inactivo conserva el proveedor', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create(['active' => false]);
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-20',
        ])
        ->assertRedirect();

    expect($purchase->refresh()->supplier_id)->toBe($supplier->id)
        ->and($purchase->invoice_date->format('Y-m-d'))->toBe('2026-07-20');
});

test('owner puede crear una compra manual sin comprobante', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice_number' => '0001-0001',
        ])
        ->assertRedirect();

    $purchase = $tenant->purchases()->firstOrFail();
    expect($purchase->invoice_image_path)->toBeNull();
});

test('crear una compra manual adjunta el comprobante', function () {
    // Los comprobantes van al disco privado (local), nunca al público.
    Storage::fake('local');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->image('ticket.jpg', 800, 600),
        ])
        ->assertRedirect();

    $purchase = $tenant->purchases()->firstOrFail();
    expect($purchase->invoice_image_path)->not->toBeNull();
    Storage::disk('local')->assertExists($purchase->invoice_image_path);
});

test('un comprobante con formato inválido es rechazado', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->create('ticket.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('invoice');

    expect($tenant->purchases()->count())->toBe(0);
});

test('editar una compra reemplaza el comprobante y borra el anterior', function () {
    // El comprobante viejo puede estar en el disco público (legacy): el nuevo
    // se guarda en el privado y el viejo se borra de donde esté.
    Storage::fake('local');
    Storage::fake('public');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    Storage::disk('public')->put("purchases/{$tenant->id}/original.jpg", 'bytes-originales');
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
        'invoice_image_path' => "purchases/{$tenant->id}/original.jpg",
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice' => UploadedFile::fake()->image('nuevo.jpg', 800, 600),
        ])
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->invoice_image_path)->not->toBe("purchases/{$tenant->id}/original.jpg");
    Storage::disk('local')->assertExists($purchase->invoice_image_path);
    Storage::disk('public')->assertMissing("purchases/{$tenant->id}/original.jpg");
});

test('editar una compra sin adjuntar archivo conserva el comprobante existente', function () {
    Storage::fake('public');
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    Storage::disk('public')->put("purchases/{$tenant->id}/original.jpg", 'bytes-originales');
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
        'invoice_image_path' => "purchases/{$tenant->id}/original.jpg",
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'notes' => 'actualizado',
        ])
        ->assertRedirect();

    $purchase->refresh();
    expect($purchase->invoice_image_path)->toBe("purchases/{$tenant->id}/original.jpg");
    Storage::disk('public')->assertExists("purchases/{$tenant->id}/original.jpg");
});

// --- Facturas duplicadas ---

test('no se puede crear una compra con número de factura duplicado para el mismo proveedor', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-10',
        'invoice_number' => '0001-0099',
    ]);

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-13',
            'invoice_number' => '0001-0099',
        ])
        ->assertSessionHasErrors('invoice_number');

    expect($tenant->purchases()->count())->toBe(1);
});

test('el mismo número de factura es válido para otro proveedor u otro tenant', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $otherSupplier = Supplier::factory()->for($tenant)->create();
    $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-10',
        'invoice_number' => '0001-0099',
    ]);

    $this->actingAs($user)
        ->post(route('purchases.store'), [
            'supplier_id' => $otherSupplier->id,
            'invoice_date' => '2026-07-13',
            'invoice_number' => '0001-0099',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tenant->purchases()->count())->toBe(2);
});

test('editar una compra sin cambiar el número de factura no dispara el error de duplicado', function () {
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-10',
        'invoice_number' => '0001-0099',
    ]);

    $this->actingAs($user)
        ->patch(route('purchases.update', $purchase), [
            'supplier_id' => $supplier->id,
            'invoice_date' => '2026-07-11',
            'invoice_number' => '0001-0099',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('el match de una compra con ítem dado de baja ofrece su opción marcada', function () {
    // Si el select de asociación listara sólo activos, el renglón asociado a un
    // ingrediente dado de baja caería en "— sin asociar —" y guardarlo
    // revertiría stock y costo en silencio (regla del proveedor inactivo, v0.10.0).
    [$user, $tenant] = ownerForPurchaseCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'name' => 'Harina Baja',
        'unit' => 'kg',
        'active' => false,
    ]);
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-13',
    ]);
    $purchase->lines()->create([
        'raw_name' => 'HARINA X 25KG',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1,
        'purchase_unit' => 'kg',
        'unit_price' => 100,
        'subtotal' => 100,
    ]);

    $html = $this->actingAs($user)->get(route('purchases.match', $purchase))->assertOk()->getContent();

    expect($html)->toContain('Harina Baja')
        ->and($html)->toContain('(inactivo)')
        ->and($html)->toContain('value="ingredient:'.$ingredient->id.'"');
});

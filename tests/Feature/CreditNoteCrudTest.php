<?php

use App\Enums\TenantUserRole;
use App\Models\CreditNote;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForCreditNoteCrud(TenantUserRole $role = TenantUserRole::Owner): array
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

function creditNoteCrudPurchaseFor(Tenant $tenant, ?Supplier $supplier = null): Purchase
{
    $supplier ??= Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-07']);
}

test('owner puede crear una nota de crédito ligada a una compra', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = creditNoteCrudPurchaseFor($tenant, $supplier);

    $this->actingAs($user)
        ->post(route('credit-notes.store'), [
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'note_number' => 'NC-0001',
            'note_date' => '2026-07-15',
            'notes' => 'Mercadería que no llegó',
        ])
        ->assertRedirect();

    $note = $tenant->creditNotes()->firstOrFail();
    expect($note->supplier_id)->toBe($supplier->id)
        ->and($note->purchase_id)->toBe($purchase->id)
        ->and($note->note_number)->toBe('NC-0001');
});

test('owner puede crear una nota de crédito sin compra de origen (reconocimiento económico)', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('credit-notes.store'), [
            'supplier_id' => $supplier->id,
            'note_date' => '2026-07-15',
        ])
        ->assertRedirect();

    $note = $tenant->creditNotes()->firstOrFail();
    expect($note->purchase_id)->toBeNull();
});

test('no se puede repetir el número de nota para el mismo proveedor', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'note_number' => 'NC-0001',
        'note_date' => '2026-07-10',
    ]);

    $this->actingAs($user)
        ->post(route('credit-notes.store'), [
            'supplier_id' => $supplier->id,
            'note_number' => 'NC-0001',
            'note_date' => '2026-07-15',
        ])
        ->assertSessionHasErrors('note_number');
});

test('llegar al listado con ?purchase_id= precarga y abre el modal de alta', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = creditNoteCrudPurchaseFor($tenant, $supplier)->fresh(['supplier']);
    $purchase->update(['invoice_number' => '0001-00099887']);

    $html = $this->actingAs($user)
        ->get(route('credit-notes.index', ['purchase_id' => $purchase->id]))
        ->assertOk()
        ->getContent();

    // El bloque bloqueado del modal muestra el número de factura, y el modal
    // arranca abierto (show: true en el x-data de x-modal) en vez de esperar
    // a que el usuario haga clic en "+ Nueva nota de crédito".
    expect($html)->toContain('0001-00099887')
        ->and($html)->toContain('show: true');
});

test('llegar al listado con un purchase_id de otro tenant no precarga nada', function () {
    [$user] = ownerForCreditNoteCrud();

    $otherTenant = Tenant::factory()->create();
    $otherPurchase = creditNoteCrudPurchaseFor($otherTenant);

    $this->actingAs($user)
        ->get(route('credit-notes.index', ['purchase_id' => $otherPurchase->id]))
        ->assertOk()
        ->assertDontSee('show: true', false);
});

test('owner puede ver el listado con el modal de alta', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    creditNoteCrudPurchaseFor($tenant, $supplier);
    $tenant->creditNotes()->create(['supplier_id' => $supplier->id, 'note_date' => '2026-07-10']);

    $this->actingAs($user)->get(route('credit-notes.index'))->assertOk();
});

test('owner puede eliminar una nota de crédito directo desde el listado, sin entrar al detalle', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'note_number' => 'NC-0042',
        'note_date' => '2026-07-10',
    ]);

    $html = $this->actingAs($user)->get(route('credit-notes.index'))->assertOk()->getContent();
    expect($html)->toContain(route('credit-notes.destroy', $note));

    $this->actingAs($user)
        ->delete(route('credit-notes.destroy', $note))
        ->assertRedirect(route('credit-notes.index'));

    expect(CreditNote::find($note->id))->toBeNull();
});

test('viewer no ve la acción de eliminar en el listado', function () {
    [$user, $tenant] = ownerForCreditNoteCrud(TenantUserRole::Viewer);
    $supplier = Supplier::factory()->for($tenant)->create();
    $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'note_date' => '2026-07-10',
    ]);

    $html = $this->actingAs($user)->get(route('credit-notes.index'))->assertOk()->getContent();

    expect($html)->not->toContain('Eliminar nota de crédito');
});

test('owner puede editar y eliminar una nota de crédito', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'note_date' => '2026-07-10',
    ]);

    $this->actingAs($user)
        ->patch(route('credit-notes.update', $note), [
            'supplier_id' => $supplier->id,
            'note_number' => 'NC-9999',
            'note_date' => '2026-07-12',
        ])
        ->assertRedirect();

    expect($note->fresh()->note_number)->toBe('NC-9999');

    $this->actingAs($user)
        ->delete(route('credit-notes.destroy', $note))
        ->assertRedirect(route('credit-notes.index'));

    expect(CreditNote::find($note->id))->toBeNull();
});

test('viewer no puede crear una nota de crédito', function () {
    [$user, $tenant] = ownerForCreditNoteCrud(TenantUserRole::Viewer);
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('credit-notes.store'), [
            'supplier_id' => $supplier->id,
            'note_date' => '2026-07-15',
        ])
        ->assertForbidden();
});

test('owner puede agregar y eliminar un renglón desde el detalle', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = creditNoteCrudPurchaseFor($tenant, $supplier);
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'purchase_id' => $purchase->id,
        'note_date' => '2026-07-15',
    ]);

    // Renderiza el detalle con los modales de owner (agregar renglón, editar cabecera).
    $this->actingAs($user)->get(route('credit-notes.show', $note))->assertOk();

    $this->actingAs($user)
        ->post(route('credit-notes.lines.store', $note), [
            'purchase_line_id' => null,
            'description' => 'Reconocimiento por rotura',
            'quantity' => 1,
            'unit' => 'u',
            'unit_price' => 5000,
        ])
        ->assertRedirect();

    $line = $note->lines()->firstOrFail();
    expect($line->description)->toBe('Reconocimiento por rotura');

    $this->actingAs($user)
        ->delete(route('credit-notes.lines.destroy', [$note, $line]))
        ->assertRedirect();

    expect($note->lines()->count())->toBe(0);
});

test('viewer puede ver el listado y el detalle', function () {
    [$user, $tenant] = ownerForCreditNoteCrud(TenantUserRole::Viewer);
    $supplier = Supplier::factory()->for($tenant)->create();
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'note_date' => '2026-07-10',
    ]);

    $this->actingAs($user)->get(route('credit-notes.index'))->assertOk();
    $this->actingAs($user)->get(route('credit-notes.show', $note))->assertOk();
});

test('el listado y el detalle tienen un x-data ancestro para que los botones abran los modales', function () {
    // Regresión: sin un x-data en el div contenedor, los @click="$dispatch('open-modal', ...)"
    // de "+ Nueva nota de crédito", "Editar" y "+ Agregar renglón" son directivas muertas —
    // Alpine sólo procesa @click dentro del árbol de un x-data ancestro. Los tests HTTP no lo
    // detectan porque no ejecutan JS; esto es lo más cerca que se puede verificar sin un
    // browser test real.
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = creditNoteCrudPurchaseFor($tenant, $supplier);
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'purchase_id' => $purchase->id,
        'note_date' => '2026-07-10',
    ]);

    $indexHtml = $this->actingAs($user)->get(route('credit-notes.index'))->assertOk()->getContent();
    $showHtml = $this->actingAs($user)->get(route('credit-notes.show', $note))->assertOk()->getContent();

    // El div que envuelve el botón de alta, y el que envuelve "Editar"/"Agregar
    // renglón", tienen que tener `x-data` antes del primer `open-modal`.
    expect(strpos($indexHtml, 'x-data'))->not->toBeFalse()
        ->and(strpos($indexHtml, 'x-data'))->toBeLessThan(strpos($indexHtml, "open-modal', 'credit-note-create'"));

    expect(strpos($showHtml, 'x-data'))->not->toBeFalse()
        ->and(strpos($showHtml, 'x-data'))->toBeLessThan(strpos($showHtml, "open-modal', 'credit-note-add-line'"));
});

test('aislamiento: una nota de crédito de otro tenant responde 404', function () {
    [$user] = ownerForCreditNoteCrud();

    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->for($otherTenant)->create();
    $otherNote = $otherTenant->creditNotes()->create([
        'supplier_id' => $otherSupplier->id,
        'note_date' => '2026-07-10',
    ]);

    $this->actingAs($user)->get(route('credit-notes.show', $otherNote))->assertNotFound();
});

test('la compra de origen muestra la nota de crédito en su detalle', function () {
    [$user, $tenant] = ownerForCreditNoteCrud();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = creditNoteCrudPurchaseFor($tenant, $supplier);
    $note = $tenant->creditNotes()->create([
        'supplier_id' => $supplier->id,
        'purchase_id' => $purchase->id,
        'note_number' => 'NC-0007',
        'note_date' => '2026-07-15',
    ]);

    $this->actingAs($user)
        ->get(route('purchases.show', $purchase))
        ->assertOk()
        ->assertSee('NC-0007');
});

test('aislamiento: notas de crédito de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForCreditNoteCrud();

    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->for($otherTenant)->create(['name' => 'Ajeno']);
    $otherTenant->creditNotes()->create([
        'supplier_id' => $otherSupplier->id,
        'note_number' => 'NC-AJENA',
        'note_date' => '2026-07-10',
    ]);

    $this->actingAs($user)
        ->get(route('credit-notes.index'))
        ->assertDontSee('NC-AJENA');
});

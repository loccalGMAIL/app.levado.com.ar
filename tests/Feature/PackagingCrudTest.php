<?php

use App\Enums\TenantUserRole;
use App\Models\Packaging;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForPackaging(): array
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

test('owner puede listar envases', function () {
    [$user, $tenant] = ownerForPackaging();
    Packaging::factory()->for($tenant)->create(['name' => 'Bolsa kraft']);

    $this->actingAs($user)
        ->get(route('packaging.index'))
        ->assertOk()
        ->assertSee('Bolsa kraft');
});

test('viewer puede ver la lista de envases', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    Packaging::factory()->for($tenant)->create(['name' => 'Caja medialuna']);

    $this->actingAs($user)
        ->get(route('packaging.index'))
        ->assertOk()
        ->assertSee('Caja medialuna');
});

// --- Proveedor dado de baja ---

test('editar un envase cuyo proveedor está inactivo no borra el proveedor', function () {
    // Mismo motivo que en ingredientes: el select de edición debe listar los inactivos o
    // caería en «— Ninguno —» y guardar cualquier otro campo perdería el proveedor.
    [$user, $tenant] = ownerForPackaging();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Proveedor Baja', 'active' => false]);
    $packaging = Packaging::factory()->for($tenant)->create([
        'supplier_id' => $supplier->id,
        'cost_per_unit' => '10',
    ]);

    // Mirar el select de edición puntualmente: el nombre del proveedor también se renderiza
    // en la tabla, así que un assertSee suelto pasaría aunque el select estuviera roto.
    $html = $this->actingAs($user)->get(route('packaging.index'))->assertOk()->getContent();
    $editSelect = str($html)->after('id="edit_pkg_supplier"')->before('</select>')->toString();

    expect($editSelect)->toContain('Proveedor Baja')
        ->and($editSelect)->toContain('(inactivo)')
        ->and($editSelect)->toContain('value="'.$supplier->id.'"');

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => $packaging->name,
            'cost_per_unit' => '35',
            'supplier_id' => $supplier->id,
        ])
        ->assertRedirect(route('packaging.index'));

    expect($packaging->refresh()->supplier_id)->toBe($supplier->id)
        ->and((float) $packaging->cost_per_unit)->toBe(35.0);
});

test('el alta de envases no ofrece proveedores inactivos', function () {
    [$user, $tenant] = ownerForPackaging();
    Supplier::factory()->for($tenant)->create(['name' => 'ProveedorActivo', 'active' => true]);
    Supplier::factory()->for($tenant)->create(['name' => 'ProveedorInactivo', 'active' => false]);

    $html = $this->actingAs($user)->get(route('packaging.index'))->assertOk()->getContent();
    expect($html)->toContain('id="create_pkg_supplier"');

    $createSelect = str($html)->after('id="create_pkg_supplier"')->before('</select>')->toString();

    expect($createSelect)->toContain('ProveedorActivo')
        ->and($createSelect)->not->toContain('ProveedorInactivo');
});

// --- Aislamiento del proveedor ---

test('aislamiento: no se puede asignar un proveedor de otro tenant a un envase', function () {
    [$user] = ownerForPackaging();

    $otherTenant = Tenant::factory()->create();
    $otherSupplier = Supplier::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Con proveedor ajeno',
            'cost_per_unit' => '10',
            'supplier_id' => $otherSupplier->id,
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('owner puede crear un envase', function () {
    [$user, $tenant] = ownerForPackaging();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Bolsa celofán',
            'cost_per_unit' => '12.50',
        ])
        ->assertRedirect(route('packaging.index'));

    expect($tenant->packagings()->where('name', 'Bolsa celofán')->exists())->toBeTrue();
});

test('admin puede crear un envase', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Admin->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Etiqueta adhesiva',
            'cost_per_unit' => '3.00',
        ])
        ->assertRedirect(route('packaging.index'));

    expect($tenant->packagings()->where('name', 'Etiqueta adhesiva')->exists())->toBeTrue();
});

test('viewer no puede crear envases', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('packaging.store'), ['name' => 'Caja', 'cost_per_unit' => '10'])
        ->assertForbidden();
});

test('nombre es obligatorio al crear envase', function () {
    [$user] = ownerForPackaging();

    $this->actingAs($user)
        ->post(route('packaging.store'), ['cost_per_unit' => '10'])
        ->assertSessionHasErrors('name');
});

test('owner puede editar un envase', function () {
    [$user, $tenant] = ownerForPackaging();
    $packaging = Packaging::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => 'Actualizado',
            'cost_per_unit' => $packaging->cost_per_unit,
        ])
        ->assertRedirect(route('packaging.index'));

    expect($packaging->fresh()->name)->toBe('Actualizado');
});

test('owner puede desactivar un envase', function () {
    [$user, $tenant] = ownerForPackaging();
    $packaging = Packaging::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('packaging.toggle-active', $packaging))
        ->assertRedirect();

    expect($packaging->fresh()->active)->toBeFalse();
});

test('aislamiento: envases de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForPackaging();

    $otherTenant = Tenant::factory()->create();
    Packaging::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    $this->actingAs($user)
        ->get(route('packaging.index'))
        ->assertDontSee('Ajeno');
});

test('owner puede crear envase con subdivisiones', function () {
    [$user, $tenant] = ownerForPackaging();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Bolsas kraft x100',
            'cost_per_unit' => '2000',
            'subdivisions' => 100,
            'subdivision_label' => 'bolsa',
        ])
        ->assertRedirect(route('packaging.index'));

    $packaging = $tenant->packagings()->where('name', 'Bolsas kraft x100')->first();
    expect($packaging->subdivisions)->toBe(100);
    expect($packaging->subdivision_label)->toBe('bolsa');
    expect((float) $packaging->cost_per_package)->toBe(2000.0)
        ->and((float) $packaging->cost_per_unit)->toBe(20.0);
});

test('crear envase sin subdivisiones mantiene null', function () {
    [$user, $tenant] = ownerForPackaging();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Caja simple',
            'cost_per_unit' => '5.00',
        ])
        ->assertRedirect(route('packaging.index'));

    $packaging = $tenant->packagings()->where('name', 'Caja simple')->first();
    expect($packaging->subdivisions)->toBeNull();
    expect($packaging->subdivision_label)->toBeNull();
    expect($packaging->cost_per_package)->toBeNull()
        ->and((float) $packaging->cost_per_unit)->toBe(5.0);
});

test('owner puede editar envase agregando subdivisiones', function () {
    [$user, $tenant] = ownerForPackaging();
    $packaging = Packaging::factory()->for($tenant)->create([
        'name' => 'Caja sin subdivisión',
        'cost_per_unit' => '1.00',
    ]);

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => 'Caja sin subdivisión',
            'cost_per_unit' => '100',
            'subdivisions' => 4,
            'subdivision_label' => 'compartimento',
        ])
        ->assertRedirect(route('packaging.index'));

    $packaging->refresh();

    expect($packaging->subdivisions)->toBe(4);
    expect($packaging->subdivision_label)->toBe('compartimento');
    expect((float) $packaging->cost_per_package)->toBe(100.0)
        ->and((float) $packaging->cost_per_unit)->toBe(25.0);
});

test('quitar subdivisiones al editar un envase limpia cost_per_package', function () {
    [$user, $tenant] = ownerForPackaging();
    $packaging = Packaging::factory()->for($tenant)->create([
        'cost_per_unit' => '20',
        'cost_per_package' => '2000',
        'subdivisions' => 100,
        'subdivision_label' => 'bolsa',
    ]);

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => $packaging->name,
            'cost_per_unit' => '50',
        ])
        ->assertRedirect(route('packaging.index'));

    $packaging->refresh();

    expect($packaging->cost_per_package)->toBeNull()
        ->and((float) $packaging->cost_per_unit)->toBe(50.0);
});

test('subdivisions debe ser al menos 2', function () {
    [$user] = ownerForPackaging();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Caja',
            'cost_per_unit' => '10',
            'subdivisions' => 1,
        ])
        ->assertSessionHasErrors('subdivisions');
});

test('aislamiento: no se puede editar envase de otro tenant', function () {
    [$user] = ownerForPackaging();

    $otherTenant = Tenant::factory()->create();
    $other = Packaging::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('packaging.update', $other), [
            'name' => 'Hack',
            'cost_per_unit' => '1',
        ])
        ->assertNotFound();
});

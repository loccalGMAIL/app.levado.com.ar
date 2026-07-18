<?php

use App\Enums\TenantUserRole;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForSupplier(): array
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

// --- Listado ---

test('owner puede listar proveedores', function () {
    [$user, $tenant] = ownerForSupplier();
    Supplier::factory()->for($tenant)->create(['name' => 'Distribuidora Sol']);

    $this->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('Distribuidora Sol');
});

// --- Crear ---

test('owner puede crear un proveedor', function () {
    [$user, $tenant] = ownerForSupplier();

    $this->actingAs($user)
        ->post(route('suppliers.store'), [
            'name' => 'Proveedor Nuevo',
            'phone' => '11-4444-5555',
            'email' => 'proveedor@example.com',
        ])
        ->assertRedirect();

    expect($tenant->suppliers()->where('name', 'Proveedor Nuevo')->exists())->toBeTrue();
});

test('admin puede crear un proveedor', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Admin->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('suppliers.store'), ['name' => 'Admin Proveedor'])
        ->assertRedirect();

    expect($tenant->suppliers()->where('name', 'Admin Proveedor')->exists())->toBeTrue();
});

test('viewer no puede crear proveedores', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('suppliers.store'), ['name' => 'Intento'])
        ->assertForbidden();
});

test('nombre es obligatorio al crear proveedor', function () {
    [$user] = ownerForSupplier();

    $this->actingAs($user)
        ->post(route('suppliers.store'), ['phone' => '1234'])
        ->assertSessionHasErrors('name');
});

// --- Editar ---

test('owner puede editar un proveedor', function () {
    [$user, $tenant] = ownerForSupplier();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('suppliers.update', $supplier), ['name' => 'Actualizado'])
        ->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->name)->toBe('Actualizado');
});

// --- Toggle ---

test('owner puede desactivar un proveedor', function () {
    [$user, $tenant] = ownerForSupplier();
    $supplier = Supplier::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('suppliers.toggle-active', $supplier))
        ->assertRedirect();

    expect($supplier->fresh()->active)->toBeFalse();
});

// --- Aislamiento ---

test('aislamiento: proveedores de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForSupplier();

    $otherTenant = Tenant::factory()->create();
    Supplier::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    $this->actingAs($user)
        ->get(route('suppliers.index'))
        ->assertDontSee('Ajeno');
});

test('aislamiento: no se puede editar proveedor de otro tenant', function () {
    [$user] = ownerForSupplier();

    $otherTenant = Tenant::factory()->create();
    $other = Supplier::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('suppliers.update', $other), ['name' => 'Hack'])
        ->assertNotFound();
});

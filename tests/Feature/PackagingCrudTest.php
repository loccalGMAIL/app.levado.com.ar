<?php

use App\Enums\TenantUserRole;
use App\Models\Packaging;
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

test('aislamiento: no se puede editar envase de otro tenant', function () {
    [$user] = ownerForPackaging();

    $otherTenant = Tenant::factory()->create();
    $other = Packaging::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('packaging.update', $other), [
            'name' => 'Hack',
            'cost_per_unit' => '1',
        ])
        ->assertForbidden();
});

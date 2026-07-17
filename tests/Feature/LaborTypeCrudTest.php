<?php

use App\Enums\TenantUserRole;
use App\Models\LaborType;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForLaborType(): array
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

test('owner puede listar tipos de mano de obra', function () {
    [$user, $tenant] = ownerForLaborType();
    LaborType::factory()->for($tenant)->create(['name' => 'Panadero']);

    $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertOk()
        ->assertSee('Panadero');
});

test('viewer puede ver la lista de tipos de mano de obra', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    LaborType::factory()->for($tenant)->create(['name' => 'Pastelero']);

    $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertOk()
        ->assertSee('Pastelero');
});

test('owner puede crear un tipo de mano de obra', function () {
    [$user, $tenant] = ownerForLaborType();

    $this->actingAs($user)
        ->post(route('labor-types.store'), [
            'name' => 'Maestro panadero',
            'hourly_rate' => '2500.00',
        ])
        ->assertRedirect(route('labor-types.index'));

    expect($tenant->laborTypes()->where('name', 'Maestro panadero')->exists())->toBeTrue();
});

test('admin puede crear un tipo de mano de obra', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Admin->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('labor-types.store'), [
            'name' => 'Ayudante',
            'hourly_rate' => '1200.00',
        ])
        ->assertRedirect(route('labor-types.index'));

    expect($tenant->laborTypes()->where('name', 'Ayudante')->exists())->toBeTrue();
});

test('viewer no puede crear tipos de mano de obra', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('labor-types.store'), ['name' => 'Aprendiz', 'hourly_rate' => '800'])
        ->assertForbidden();
});

test('nombre es obligatorio al crear tipo de mano de obra', function () {
    [$user] = ownerForLaborType();

    $this->actingAs($user)
        ->post(route('labor-types.store'), ['hourly_rate' => '1000'])
        ->assertSessionHasErrors('name');
});

test('hourly_rate es obligatorio al crear tipo de mano de obra', function () {
    [$user] = ownerForLaborType();

    $this->actingAs($user)
        ->post(route('labor-types.store'), ['name' => 'Panadero'])
        ->assertSessionHasErrors('hourly_rate');
});

test('owner puede editar un tipo de mano de obra', function () {
    [$user, $tenant] = ownerForLaborType();
    $laborType = LaborType::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('labor-types.update', $laborType), [
            'name' => 'Actualizado',
            'hourly_rate' => $laborType->hourly_rate,
        ])
        ->assertRedirect(route('labor-types.index'));

    expect($laborType->fresh()->name)->toBe('Actualizado');
});

test('owner puede desactivar un tipo de mano de obra', function () {
    [$user, $tenant] = ownerForLaborType();
    $laborType = LaborType::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('labor-types.toggle-active', $laborType))
        ->assertRedirect();

    expect($laborType->fresh()->active)->toBeFalse();
});

test('aislamiento: tipos de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForLaborType();

    $otherTenant = Tenant::factory()->create();
    LaborType::factory()->for($otherTenant)->create(['name' => 'AjenoLT']);

    $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertDontSee('AjenoLT');
});

test('aislamiento: no se puede editar tipo de mano de obra de otro tenant', function () {
    [$user] = ownerForLaborType();

    $otherTenant = Tenant::factory()->create();
    $other = LaborType::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('labor-types.update', $other), [
            'name' => 'Hack',
            'hourly_rate' => '1',
        ])
        ->assertNotFound();
});

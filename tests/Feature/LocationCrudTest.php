<?php

use App\Enums\TenantUserRole;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForLocation(): array
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

test('owner puede listar sus sucursales', function () {
    [$user, $tenant] = ownerForLocation();
    Location::factory()->for($tenant)->create(['name' => 'Casa Central']);

    $this->actingAs($user)
        ->get(route('locations.index'))
        ->assertOk()
        ->assertSee('Casa Central');
});

test('owner puede crear una sucursal', function () {
    [$user, $tenant] = ownerForLocation();

    $this->actingAs($user)
        ->post(route('locations.store'), [
            'name' => 'Sucursal Norte',
            'address' => 'Av. Corrientes 1234',
            'city' => 'Buenos Aires',
        ])
        ->assertRedirect(route('locations.index'));

    expect($tenant->locations()->where('name', 'Sucursal Norte')->exists())->toBeTrue();
});

test('la primera sucursal creada es siempre la principal', function () {
    [$user, $tenant] = ownerForLocation();

    $this->actingAs($user)->post(route('locations.store'), ['name' => 'Única']);

    expect($tenant->locations()->first()->is_default)->toBeTrue();
});

test('marcar is_default en create quita el flag de las existentes', function () {
    [$user, $tenant] = ownerForLocation();
    $existing = Location::factory()->for($tenant)->default()->create();

    $this->actingAs($user)->post(route('locations.store'), [
        'name' => 'Nueva Principal',
        'is_default' => '1',
    ]);

    expect($existing->fresh()->is_default)->toBeFalse();
    expect($tenant->locations()->where('name', 'Nueva Principal')->first()->is_default)->toBeTrue();
});

test('owner puede editar su sucursal', function () {
    [$user, $tenant] = ownerForLocation();
    $location = Location::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('locations.update', $location), ['name' => 'Actualizada'])
        ->assertRedirect(route('locations.index'));

    expect($location->fresh()->name)->toBe('Actualizada');
});

test('owner puede desactivar una sucursal no principal', function () {
    [$user, $tenant] = ownerForLocation();
    $location = Location::factory()->for($tenant)->create(['is_default' => false, 'active' => true]);

    $this->actingAs($user)
        ->patch(route('locations.toggle-active', $location))
        ->assertRedirect();

    expect($location->fresh()->active)->toBeFalse();
});

test('no se puede desactivar la sucursal principal', function () {
    [$user, $tenant] = ownerForLocation();
    $location = Location::factory()->for($tenant)->default()->create();

    $this->actingAs($user)
        ->patch(route('locations.toggle-active', $location))
        ->assertRedirect();

    expect($location->fresh()->active)->toBeTrue();
});

test('aislamiento: owner no puede ver sucursales de otro tenant', function () {
    [$user] = ownerForLocation();

    $otherTenant = Tenant::factory()->create();
    $otherLocation = Location::factory()->for($otherTenant)->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->get(route('locations.index'))
        ->assertDontSee('Ajena');
});

test('aislamiento: owner no puede editar sucursal de otro tenant', function () {
    [$user] = ownerForLocation();

    $otherTenant = Tenant::factory()->create();
    $otherLocation = Location::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('locations.update', $otherLocation), ['name' => 'Hack'])
        ->assertNotFound();
});

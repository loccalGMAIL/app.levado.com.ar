<?php

use App\Enums\TenantUserRole;
use App\Models\DeliveryPerson;
use App\Models\Tenant;

test('owner puede listar sus repartidores', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    DeliveryPerson::factory()->for($tenant)->create(['name' => 'Juan Reparto']);

    $this->actingAs($user)
        ->get(route('reparto.repartidores.index'))
        ->assertOk()
        ->assertSee('Juan Reparto');
});

test('admin puede acceder a repartidores', function () {
    [$user] = tenantUserAs(TenantUserRole::Admin);

    $this->actingAs($user)
        ->get(route('reparto.repartidores.index'))
        ->assertOk();
});

test('owner puede crear un repartidor', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('reparto.repartidores.store'), [
            'name' => 'Carlos Delivery',
            'phone' => '11-2345-6789',
        ])
        ->assertRedirect(route('reparto.repartidores.index'));

    expect($tenant->deliveryPeople()->where('name', 'Carlos Delivery')->exists())->toBeTrue();
});

test('el nombre del repartidor es único por negocio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    DeliveryPerson::factory()->for($tenant)->create(['name' => 'Repetido']);

    $this->actingAs($user)
        ->post(route('reparto.repartidores.store'), ['name' => 'Repetido'])
        ->assertSessionHasErrors('name');
});

test('owner puede editar su repartidor', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $deliveryPerson = DeliveryPerson::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('reparto.repartidores.update', $deliveryPerson), ['name' => 'Actualizado'])
        ->assertRedirect(route('reparto.repartidores.index'));

    expect($deliveryPerson->fresh()->name)->toBe('Actualizado');
});

test('owner puede desactivar un repartidor', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $deliveryPerson = DeliveryPerson::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('reparto.repartidores.toggle-active', $deliveryPerson))
        ->assertRedirect();

    expect($deliveryPerson->fresh()->active)->toBeFalse();
});

test('viewer no puede acceder a repartidores', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->get(route('reparto.repartidores.index'))
        ->assertForbidden();
});

test('aislamiento: owner no puede ver repartidores de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    DeliveryPerson::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    $this->actingAs($user)
        ->get(route('reparto.repartidores.index'))
        ->assertDontSee('Ajeno');
});

test('aislamiento: owner no puede editar repartidor de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    $otherDeliveryPerson = DeliveryPerson::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('reparto.repartidores.update', $otherDeliveryPerson), ['name' => 'Hack'])
        ->assertNotFound();
});

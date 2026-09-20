<?php

use App\Enums\TenantUserRole;
use App\Models\Customer;
use App\Models\DeliveryPerson;
use App\Models\Tenant;

test('owner puede listar sus clientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Customer::factory()->for($tenant)->create(['name' => 'Kiosco La Esquina']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Kiosco La Esquina');
});

test('owner puede crear un cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'Bar Don Pepe',
            'phone' => '11-2345-6789',
            'address' => 'Av. Siempre Viva 123',
        ])
        ->assertRedirect(route('customers.index'));

    expect($tenant->customers()->where('name', 'Bar Don Pepe')->exists())->toBeTrue();
});

test('el nombre del cliente es único por negocio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Customer::factory()->for($tenant)->create(['name' => 'Repetido']);

    $this->actingAs($user)
        ->post(route('customers.store'), ['name' => 'Repetido'])
        ->assertSessionHasErrors('name');
});

test('owner puede asignar un repartidor a cargo del cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $deliveryPerson = DeliveryPerson::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'Con repartidor',
            'delivery_person_id' => $deliveryPerson->id,
        ])
        ->assertRedirect(route('customers.index'));

    expect(Customer::where('name', 'Con repartidor')->first()->delivery_person_id)->toBe($deliveryPerson->id);
});

test('el repartidor a cargo debe ser del mismo negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    $foreignDeliveryPerson = DeliveryPerson::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'Cliente',
            'delivery_person_id' => $foreignDeliveryPerson->id,
        ])
        ->assertSessionHasErrors('delivery_person_id');
});

test('owner puede editar su cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('customers.update', $customer), ['name' => 'Actualizado'])
        ->assertRedirect(route('customers.index'));

    expect($customer->fresh()->name)->toBe('Actualizado');
});

test('owner puede desactivar un cliente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $customer = Customer::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('customers.toggle-active', $customer))
        ->assertRedirect();

    expect($customer->fresh()->active)->toBeFalse();
});

test('viewer no puede acceder a clientes', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertForbidden();
});

test('aislamiento: owner no puede ver clientes de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    Customer::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertDontSee('Ajeno');
});

test('aislamiento: owner no puede editar cliente de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('customers.update', $otherCustomer), ['name' => 'Hack'])
        ->assertNotFound();
});

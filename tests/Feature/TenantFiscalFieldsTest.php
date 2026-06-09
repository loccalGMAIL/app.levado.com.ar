<?php

use App\Enums\CondicionIva;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerWithTenant(): array
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

test('owner puede guardar campos fiscales en el negocio', function () {
    [$user, $tenant] = ownerWithTenant();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country,
            'currency' => $tenant->currency,
            'productive_hours_month' => $tenant->productive_hours_month,
            'razon_social' => 'Panadería El Trigo SRL',
            'cuit' => '30-71234567-9',
            'condicion_iva' => CondicionIva::RI->value,
            'purchase_price_includes_iva' => '1',
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->razon_social)->toBe('Panadería El Trigo SRL')
        ->and($tenant->cuit)->toBe('30-71234567-9')
        ->and($tenant->condicion_iva)->toBe(CondicionIva::RI);
});

test('campos fiscales son opcionales', function () {
    [$user, $tenant] = ownerWithTenant();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country,
            'currency' => $tenant->currency,
            'productive_hours_month' => $tenant->productive_hours_month,
            'purchase_price_includes_iva' => '1',
        ])
        ->assertRedirect();

    $tenant->refresh();

    expect($tenant->razon_social)->toBeNull()
        ->and($tenant->cuit)->toBeNull()
        ->and($tenant->condicion_iva)->toBeNull();
});

test('cuit con formato inválido es rechazado', function () {
    [$user, $tenant] = ownerWithTenant();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country,
            'currency' => $tenant->currency,
            'productive_hours_month' => $tenant->productive_hours_month,
            'cuit' => 'no-es-un-cuit',
        ])
        ->assertSessionHasErrors('cuit');
});

test('condicion_iva inválida es rechazada', function () {
    [$user, $tenant] = ownerWithTenant();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country,
            'currency' => $tenant->currency,
            'productive_hours_month' => $tenant->productive_hours_month,
            'condicion_iva' => 'INVALIDO',
        ])
        ->assertSessionHasErrors('condicion_iva');
});

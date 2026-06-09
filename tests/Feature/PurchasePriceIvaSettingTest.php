<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForPurchaseIvaSetting(): array
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

test('owner puede marcar que los precios de compra son netos sin IVA', function () {
    [$user, $tenant] = ownerForPurchaseIvaSetting();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country ?? 'AR',
            'currency' => $tenant->currency ?? 'ARS',
            'productive_hours_month' => $tenant->productive_hours_month ?? 160,
            'purchase_price_includes_iva' => '0',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->getSetting('purchase_price_includes_iva'))->toBe('0');
});

test('owner puede marcar que los precios de compra incluyen IVA', function () {
    [$user, $tenant] = ownerForPurchaseIvaSetting();
    $tenant->setSetting('purchase_price_includes_iva', '0');

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country ?? 'AR',
            'currency' => $tenant->currency ?? 'ARS',
            'productive_hours_month' => $tenant->productive_hours_month ?? 160,
            'purchase_price_includes_iva' => '1',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->getSetting('purchase_price_includes_iva'))->toBe('1');
});

test('un valor inválido para la preferencia de IVA es rechazado', function () {
    [$user, $tenant] = ownerForPurchaseIvaSetting();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country ?? 'AR',
            'currency' => $tenant->currency ?? 'ARS',
            'productive_hours_month' => $tenant->productive_hours_month ?? 160,
            'purchase_price_includes_iva' => 'maybe',
        ])
        ->assertSessionHasErrors('purchase_price_includes_iva');
});

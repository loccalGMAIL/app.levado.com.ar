<?php

use App\Enums\TenantUserRole;
use App\Models\Packaging;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForPackagingCost(): array
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

test('owner puede actualizar el costo inline y recibe JSON', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '12.50'])
        ->assertOk()
        ->assertJsonStructure(['cost_per_unit', 'cost_per_unit_formatted']);

    expect($packaging->fresh()->cost_per_unit)->toEqual('12.5000');
});

test('actualizar el costo crea un price log cuando el valor cambia', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '8.00'])
        ->assertOk();

    expect($packaging->priceLogs()->where('cost_per_unit', 8.00)->exists())->toBeTrue();
});

test('no crea price log cuando el costo no cambia', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '5.00'])
        ->assertOk();

    expect($packaging->priceLogs()->count())->toBe(0);
});

test('viewer no puede actualizar el costo inline', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '12.50'])
        ->assertForbidden();
});

test('no se puede actualizar el costo de un packaging de otro tenant', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $otherTenant = Tenant::factory()->create();
    $packaging = Packaging::factory()->for($otherTenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '12.50'])
        ->assertNotFound();
});

test('costo negativo retorna error de validacion', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), ['cost_per_unit' => '-1'])
        ->assertUnprocessable();
});

test('costo ausente retorna error de validacion', function () {
    [$user, $tenant] = ownerForPackagingCost();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 5.00]);

    $this->actingAs($user)
        ->patchJson(route('packaging.cost.update', $packaging), [])
        ->assertUnprocessable();
});

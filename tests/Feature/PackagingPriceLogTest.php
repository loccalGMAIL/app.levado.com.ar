<?php

use App\Enums\TenantUserRole;
use App\Models\Packaging;
use App\Models\PackagingPriceLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForPackagingLog(): array
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

test('al crear un envase se registra un price log', function () {
    [$user, $tenant] = ownerForPackagingLog();

    $this->actingAs($user)
        ->post(route('packaging.store'), [
            'name' => 'Bolsa kraft',
            'cost_per_unit' => '15.00',
        ]);

    $packaging = $tenant->packagings()->where('name', 'Bolsa kraft')->first();

    expect($packaging->priceLogs()->count())->toBe(1);
    expect((float) $packaging->priceLogs()->first()->cost_per_unit)->toBe(15.0);
});

test('al actualizar el precio se registra un nuevo price log', function () {
    [$user, $tenant] = ownerForPackagingLog();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => '10.0000']);

    $packaging->priceLogs()->create([
        'cost_per_unit' => '10.0000',
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => $packaging->name,
            'cost_per_unit' => '20.00',
        ]);

    expect($packaging->priceLogs()->count())->toBe(2);
    expect((float) $packaging->priceLogs()->orderByDesc('id')->first()->cost_per_unit)->toBe(20.0);
});

test('al actualizar sin cambiar el precio no se genera un nuevo log', function () {
    [$user, $tenant] = ownerForPackagingLog();
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => '10.0000']);

    $packaging->priceLogs()->create([
        'cost_per_unit' => '10.0000',
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('packaging.update', $packaging), [
            'name' => 'Nombre nuevo',
            'cost_per_unit' => '10.00',
        ]);

    expect($packaging->priceLogs()->count())->toBe(1);
});

test('los logs de packaging no tienen timestamps', function () {
    $log = new PackagingPriceLog;
    expect($log->timestamps)->toBeFalse();
});

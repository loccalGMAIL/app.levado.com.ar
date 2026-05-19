<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\IngredientPriceLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForPriceLog(): array
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

test('al crear un ingrediente se registra un price log', function () {
    [$user, $tenant] = ownerForPriceLog();

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Harina',
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '1200.00',
        ]);

    $ingredient = $tenant->ingredients()->where('name', 'Harina')->first();

    expect($ingredient->priceLogs()->count())->toBe(1);
    expect((float) $ingredient->priceLogs()->first()->cost_per_unit)->toBe(1200.0);
});

test('al actualizar el precio se registra un nuevo price log', function () {
    [$user, $tenant] = ownerForPriceLog();
    $ingredient = Ingredient::factory()->for($tenant)->create(['cost_per_unit' => '1000.0000']);

    $ingredient->priceLogs()->create([
        'cost_per_unit' => '1000.0000',
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('ingredients.update', $ingredient), [
            'name' => $ingredient->name,
            'unit' => $ingredient->unit->value,
            'cost_per_unit' => '1500.00',
        ]);

    expect($ingredient->priceLogs()->count())->toBe(2);
    expect((float) $ingredient->priceLogs()->orderByDesc('id')->first()->cost_per_unit)->toBe(1500.0);
});

test('al actualizar sin cambiar el precio no se genera un nuevo log', function () {
    [$user, $tenant] = ownerForPriceLog();
    $ingredient = Ingredient::factory()->for($tenant)->create(['cost_per_unit' => '1000.0000']);

    $ingredient->priceLogs()->create([
        'cost_per_unit' => '1000.0000',
        'recorded_at' => now(),
    ]);

    $this->actingAs($user)
        ->put(route('ingredients.update', $ingredient), [
            'name' => 'Nombre nuevo',
            'unit' => $ingredient->unit->value,
            'cost_per_unit' => '1000.00',
        ]);

    expect($ingredient->priceLogs()->count())->toBe(1);
});

test('los logs no tienen updated_at', function () {
    $log = new IngredientPriceLog;
    expect($log->timestamps)->toBeFalse();
});

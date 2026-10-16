<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function userForSemiOptions(TenantUserRole $role = TenantUserRole::Owner): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('devuelve solo las sub-recetas del tenant actual', function () {
    [$user, $tenant] = userForSemiOptions();
    Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Dulce de leche']);
    Recipe::factory()->for($tenant)->create(['name' => 'Torta final']);
    $otherTenant = Tenant::factory()->create();
    Recipe::factory()->for($otherTenant)->semiElaborate()->create(['name' => 'Crema ajena']);

    $names = collect($this->actingAs($user)->getJson(route('recipes.semi-options'))->assertOk()->json())
        ->pluck('name');

    expect($names->all())->toBe(['Dulce de leche']);
});

test('respeta exclude y el filtro q', function () {
    [$user, $tenant] = userForSemiOptions();
    $crema = Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Crema pastelera']);
    Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Crema chantilly']);
    Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Ganache']);

    $filtered = $this->actingAs($user)->getJson(route('recipes.semi-options', ['q' => 'crema']))->assertOk()->json();
    expect(collect($filtered)->pluck('name')->all())->toBe(['Crema chantilly', 'Crema pastelera']);

    $excluded = $this->actingAs($user)->getJson(route('recipes.semi-options', ['q' => 'crema', 'exclude' => $crema->id]))->assertOk()->json();
    expect(collect($excluded)->pluck('name')->all())->toBe(['Crema chantilly']);
});

test('incluye las inactivas marcadas como tales y la unidad de rendimiento', function () {
    [$user, $tenant] = userForSemiOptions();
    Recipe::factory()->for($tenant)->semiElaborate()->create([
        'name' => 'Masa vieja',
        'active' => false,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Masa nueva', 'active' => true]);

    $options = collect($this->actingAs($user)->getJson(route('recipes.semi-options'))->assertOk()->json())
        ->keyBy('name');

    expect($options['Masa vieja']['is_active'])->toBeFalse()
        ->and($options['Masa vieja']['unit_short'])->toBe(Unit::Kilogramo->short())
        ->and($options['Masa nueva']['is_active'])->toBeTrue();
});

test('devuelve como máximo 50 opciones ordenadas por nombre', function () {
    [$user, $tenant] = userForSemiOptions();
    foreach (range(1, 55) as $i) {
        Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => sprintf('Sub %02d', $i)]);
    }

    $names = collect($this->actingAs($user)->getJson(route('recipes.semi-options'))->assertOk()->json())
        ->pluck('name');

    expect($names)->toHaveCount(50)
        ->and($names->first())->toBe('Sub 01')
        ->and($names->last())->toBe('Sub 50');
});

test('un viewer no puede pedir las opciones', function () {
    [$user, $tenant] = userForSemiOptions(TenantUserRole::Viewer);
    Recipe::factory()->for($tenant)->semiElaborate()->create();

    $this->actingAs($user)->getJson(route('recipes.semi-options'))->assertForbidden();
});

test('el índice de recetas ya no precarga las sub-recetas en el modal', function () {
    [$user, $tenant] = userForSemiOptions();
    $semi = Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Relleno de frutilla']);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertSee('data-remote="'.route('recipes.semi-options').'"', false)
        ->assertDontSee('<option value="'.$semi->id.'">', false);
});

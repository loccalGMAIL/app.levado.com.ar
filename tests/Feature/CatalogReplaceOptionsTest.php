<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/**
 * Opciones bajo demanda de los modales «Reemplazar» de ingredientes y
 * descartables (mismo contrato que recipes.semi-options).
 */
function userForReplaceOptions(TenantUserRole $role = TenantUserRole::Owner): array
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

dataset('replace options kinds', [
    'ingredientes' => ['ingredients.replace-options', Ingredient::class, 'ingredients.index'],
    'descartables' => ['packaging.replace-options', Packaging::class, 'packaging.index'],
]);

test('devuelve solo los ítems del tenant actual', function (string $routeName, string $model) {
    [$user, $tenant] = userForReplaceOptions();
    $model::factory()->for($tenant)->create(['name' => 'Propio']);
    $model::factory()->for(Tenant::factory()->create())->create(['name' => 'Ajeno']);

    $names = collect($this->actingAs($user)->getJson(route($routeName))->assertOk()->json())->pluck('name');

    expect($names->all())->toBe(['Propio']);
})->with('replace options kinds');

test('respeta exclude y el filtro q', function (string $routeName, string $model) {
    [$user, $tenant] = userForReplaceOptions();
    $harina = $model::factory()->for($tenant)->create(['name' => 'Harina 000']);
    $model::factory()->for($tenant)->create(['name' => 'Harina integral']);
    $model::factory()->for($tenant)->create(['name' => 'Azúcar']);

    $filtered = $this->actingAs($user)->getJson(route($routeName, ['q' => 'harina']))->assertOk()->json();
    expect(collect($filtered)->pluck('name')->all())->toBe(['Harina 000', 'Harina integral']);

    $excluded = $this->actingAs($user)->getJson(route($routeName, ['q' => 'harina', 'exclude' => $harina->id]))->assertOk()->json();
    expect(collect($excluded)->pluck('name')->all())->toBe(['Harina integral']);
})->with('replace options kinds');

test('incluye los inactivos marcados como tales', function (string $routeName, string $model) {
    [$user, $tenant] = userForReplaceOptions();
    $model::factory()->for($tenant)->create(['name' => 'Viejo', 'active' => false]);
    $model::factory()->for($tenant)->create(['name' => 'Nuevo', 'active' => true]);

    $options = collect($this->actingAs($user)->getJson(route($routeName))->assertOk()->json())->keyBy('name');

    expect($options['Viejo']['is_active'])->toBeFalse()
        ->and($options['Nuevo']['is_active'])->toBeTrue();
})->with('replace options kinds');

test('devuelve como máximo 50 opciones ordenadas por nombre', function (string $routeName, string $model) {
    [$user, $tenant] = userForReplaceOptions();
    foreach (range(1, 55) as $i) {
        $model::factory()->for($tenant)->create(['name' => sprintf('Ítem %02d', $i)]);
    }

    $names = collect($this->actingAs($user)->getJson(route($routeName))->assertOk()->json())->pluck('name');

    expect($names)->toHaveCount(50)
        ->and($names->first())->toBe('Ítem 01')
        ->and($names->last())->toBe('Ítem 50');
})->with('replace options kinds');

test('un viewer no puede pedir las opciones', function (string $routeName) {
    [$user] = userForReplaceOptions(TenantUserRole::Viewer);

    $this->actingAs($user)->getJson(route($routeName))->assertForbidden();
})->with(['ingredients.replace-options', 'packaging.replace-options']);

test('el índice ya no precarga las opciones en el modal', function (string $routeName, string $model, string $indexRoute) {
    [$user, $tenant] = userForReplaceOptions();
    $item = $model::factory()->for($tenant)->create(['name' => 'Insumo precargado']);

    $this->actingAs($user)
        ->get(route($indexRoute))
        ->assertOk()
        ->assertSee('data-remote="'.route($routeName).'"', false)
        ->assertDontSee('<option value="'.$item->id.'">', false);
})->with('replace options kinds');

test('los ingredientes devuelven la unidad y excluyen los convertidos a producto', function () {
    [$user, $tenant] = userForReplaceOptions();
    Ingredient::factory()->for($tenant)->create(['name' => 'Leche', 'unit' => Unit::Litro->value]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    Ingredient::factory()->for($tenant)->create(['name' => 'Convertido', 'converted_to_product_id' => $product->id]);

    $options = collect($this->actingAs($user)->getJson(route('ingredients.replace-options'))->assertOk()->json());

    expect($options->pluck('name')->all())->toBe(['Leche'])
        ->and($options->first()['unit_short'])->toBe(Unit::Litro->short());
});

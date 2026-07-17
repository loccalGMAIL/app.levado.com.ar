<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForRecipe(): array
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

test('owner puede listar recetas', function () {
    [$user, $tenant] = ownerForRecipe();
    Recipe::factory()->for($tenant)->create(['name' => 'Medialunas']);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertSee('Medialunas');
});

test('viewer puede ver la lista de recetas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    Recipe::factory()->for($tenant)->create(['name' => 'Pan de campo']);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertSee('Pan de campo');
});

test('owner puede crear una receta', function () {
    [$user, $tenant] = ownerForRecipe();

    $this->actingAs($user)
        ->post(route('recipes.store'), [
            'name' => 'Budín de limón',
            'yield_quantity' => '12',
            'yield_unit' => 'u',
        ])
        ->assertRedirect();

    expect($tenant->recipes()->where('name', 'Budín de limón')->exists())->toBeTrue();
});

test('viewer no puede crear recetas', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('recipes.store'), ['name' => 'Hack', 'yield_quantity' => '1', 'yield_unit' => 'u'])
        ->assertForbidden();
});

test('nombre es obligatorio al crear receta', function () {
    [$user] = ownerForRecipe();

    $this->actingAs($user)
        ->post(route('recipes.store'), ['yield_quantity' => '10', 'yield_unit' => 'u'])
        ->assertSessionHasErrors('name');
});

test('yield_quantity es obligatorio al crear receta', function () {
    [$user] = ownerForRecipe();

    $this->actingAs($user)
        ->post(route('recipes.store'), ['name' => 'Pan', 'yield_unit' => 'u'])
        ->assertSessionHasErrors('yield_quantity');
});

test('owner puede ver detalle de receta', function () {
    [$user, $tenant] = ownerForRecipe();
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Tarta de manzana']);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Tarta de manzana');
});

test('owner puede editar info de receta', function () {
    [$user, $tenant] = ownerForRecipe();
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'name' => 'Actualizada',
            'yield_quantity' => $recipe->yield_quantity,
            'yield_unit' => $recipe->yield_unit->value,
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh()->name)->toBe('Actualizada');
});

test('owner puede desactivar una receta', function () {
    [$user, $tenant] = ownerForRecipe();
    $recipe = Recipe::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('recipes.toggle-active', $recipe))
        ->assertRedirect();

    expect($recipe->fresh()->active)->toBeFalse();
});

test('aislamiento: recetas de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForRecipe();

    $otherTenant = Tenant::factory()->create();
    Recipe::factory()->for($otherTenant)->create(['name' => 'AjenaRecipe']);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertDontSee('AjenaRecipe');
});

test('aislamiento: no se puede editar receta de otro tenant', function () {
    [$user] = ownerForRecipe();

    $otherTenant = Tenant::factory()->create();
    $other = Recipe::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $other), [
            'name' => 'Hack',
            'yield_quantity' => '1',
            'yield_unit' => 'u',
        ])
        ->assertForbidden();
});

test('aislamiento: no se puede ver receta de otro tenant', function () {
    [$user] = ownerForRecipe();

    $otherTenant = Tenant::factory()->create();
    $other = Recipe::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.show', $other))
        ->assertForbidden();
});

test('aislamiento: una línea de otra receta no es alcanzable (scoped binding)', function () {
    [$user, $tenant] = ownerForRecipe();

    $recipe = Recipe::factory()->for($tenant)->create();
    $otherRecipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);
    $foreignLine = $otherRecipe->ingredientLines()->create([
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => 'kg',
    ]);

    // La línea pertenece a $otherRecipe: pedirla bajo $recipe debe dar 404,
    // el binding se resuelve dentro de la relación del padre.
    $this->actingAs($user)
        ->delete(route('recipes.ingredient-lines.destroy', [$recipe, $foreignLine]))
        ->assertNotFound();

    expect($otherRecipe->ingredientLines()->count())->toBe(1);
});

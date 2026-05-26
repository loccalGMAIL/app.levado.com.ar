<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Recipe;
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForSubrecipe(): array
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

test('owner can add a sub-recipe line', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'yield_quantity' => 1,
        'unit_cost' => 200,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '0.5',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertRedirect(route('recipes.show', $parent));

    expect($parent->subrecipeLines()->where('child_recipe_id', $child->id)->exists())->toBeTrue();
});

test('non-semi-elaborate recipe is rejected as child', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->create(['is_semi_elaborate' => false]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertSessionHasErrors('child_recipe_id');
});

test('inactive recipe is rejected as child', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'active' => false,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertSessionHasErrors('child_recipe_id');
});

test('incompatible unit is rejected when adding sub-recipe', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '1',
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors('unit');
});

test('cycle detection: recipe cannot be its own child', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $recipe = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $recipe), [
            'child_recipe_id' => $recipe->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertSessionHasErrors('child_recipe_id');
});

test('cycle detection: ancestor cannot become a child', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $grandparent = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 100,
    ]);
    $parent = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 150,
    ]);
    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 80,
    ]);

    RecipeSubrecipeLine::create([
        'recipe_id' => $grandparent->id,
        'child_recipe_id' => $parent->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $child), [
            'child_recipe_id' => $grandparent->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertSessionHasErrors('child_recipe_id');
});

test('owner can delete a sub-recipe line', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 200,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $line = RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->delete(route('recipes.subrecipe-lines.destroy', [$parent, $line]))
        ->assertRedirect(route('recipes.show', $parent));

    expect(RecipeSubrecipeLine::find($line->id))->toBeNull();
});

test('owner can update quantity_used via PATCH', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 200,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $line = RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->patchJson(route('recipes.subrecipe-lines.update', [$parent, $line]), [
            'quantity_used' => 2.5,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect($line->fresh()->quantity_used)->toEqual('2.500');
});

test('unit_cost propagates to parent after sub-recipe line is added', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'yield_quantity' => 1,
        'unit_cost' => 500,
    ]);
    $parent = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ]);

    expect((float) $parent->fresh()->unit_cost)->toBe(50.0);
});

test('unit_cost propagates after sub-recipe line is deleted', function () {
    [$user, $tenant] = ownerForSubrecipe();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'yield_quantity' => 1,
        'unit_cost' => 500,
    ]);
    $parent = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);

    $line = RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->delete(route('recipes.subrecipe-lines.destroy', [$parent, $line]));

    expect((float) $parent->fresh()->unit_cost)->toBe(0.0);
});

test('tenant isolation: cannot add sub-recipe from another tenant', function () {
    [$user, $tenant] = ownerForSubrecipe();
    $otherTenant = Tenant::factory()->create();

    $foreignChild = Recipe::factory()->for($otherTenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $foreignChild->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertSessionHasErrors('child_recipe_id');
});

test('viewer cannot add sub-recipe line', function () {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $viewer->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create();

    $this->actingAs($viewer)
        ->post(route('recipes.subrecipe-lines.store', $parent), [
            'child_recipe_id' => $child->id,
            'quantity_used' => '1',
            'unit' => Unit::Kilogramo->value,
        ])
        ->assertForbidden();
});

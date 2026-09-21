<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePackagingLine;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForIngredientToggleGuard(): array
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

test('owner can deactivate an ingredient not used by any recipe', function () {
    [$user, $tenant] = ownerForIngredientToggleGuard();
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('ingredients.toggle-active', $ingredient))
        ->assertRedirect();

    expect($ingredient->fresh()->active)->toBeFalse();
});

test('owner can deactivate an ingredient only used by inactive recipes', function () {
    [$user, $tenant] = ownerForIngredientToggleGuard();
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => true, 'unit' => Unit::Gramo]);
    $recipe = Recipe::factory()->for($tenant)->create(['active' => false]);
    RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => Unit::Gramo->value]);

    $this->actingAs($user)
        ->patch(route('ingredients.toggle-active', $ingredient))
        ->assertRedirect();

    expect($ingredient->fresh()->active)->toBeFalse();
});

test('deactivating an ingredient is blocked when an active recipe uses it', function () {
    [$user, $tenant] = ownerForIngredientToggleGuard();
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => true, 'unit' => Unit::Gramo]);
    $recipe = Recipe::factory()->for($tenant)->create(['active' => true, 'name' => 'Pan de campo']);
    RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => Unit::Gramo->value]);

    $response = $this->actingAs($user)->patch(route('ingredients.toggle-active', $ingredient));

    $response->assertSessionHasErrors('toggle');
    expect($ingredient->fresh()->active)->toBeTrue()
        ->and(session('errors')->get('toggle')[0])->toContain('Pan de campo');
});

test('deactivating a packaging is blocked when an active recipe uses it', function () {
    [$user, $tenant] = ownerForIngredientToggleGuard();
    $packaging = Packaging::factory()->for($tenant)->create(['active' => true]);
    $recipe = Recipe::factory()->for($tenant)->create(['active' => true, 'name' => 'Caja de facturas']);
    RecipePackagingLine::create(['recipe_id' => $recipe->id, 'packaging_id' => $packaging->id, 'quantity' => 1]);

    $response = $this->actingAs($user)->patch(route('packaging.toggle-active', $packaging));

    $response->assertSessionHasErrors('toggle');
    expect($packaging->fresh()->active)->toBeTrue()
        ->and(session('errors')->get('toggle')[0])->toContain('Caja de facturas');
});

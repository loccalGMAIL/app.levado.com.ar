<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Recipe;
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForToggleGuard(): array
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

test('owner can deactivate a non-semi-elaborate recipe freely', function () {
    [$user, $tenant] = ownerForToggleGuard();

    $recipe = Recipe::factory()->for($tenant)->create(['active' => true, 'is_semi_elaborate' => false]);

    $this->actingAs($user)
        ->patch(route('recipes.toggle-active', $recipe))
        ->assertRedirect();

    expect($recipe->fresh()->active)->toBeFalse();
});

test('owner can deactivate a semi-elaborate recipe with no active parents', function () {
    [$user, $tenant] = ownerForToggleGuard();

    $recipe = Recipe::factory()->for($tenant)->semiElaborate()->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('recipes.toggle-active', $recipe))
        ->assertRedirect();

    expect($recipe->fresh()->active)->toBeFalse();
});

test('deactivating a semi-elaborate is blocked when an active parent recipe uses it', function () {
    [$user, $tenant] = ownerForToggleGuard();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'active' => true,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['active' => true, 'name' => 'Pan de campo']);

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('recipes.toggle-active', $child));

    $response->assertSessionHasErrors('toggle');
    expect($child->fresh()->active)->toBeTrue();
});

test('guard error message contains the blocking recipe name', function () {
    [$user, $tenant] = ownerForToggleGuard();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'active' => true,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['active' => true, 'name' => 'Factura de manteca']);

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $response = $this->actingAs($user)
        ->patch(route('recipes.toggle-active', $child))
        ->assertSessionHasErrors('toggle');

    expect(session('errors')->get('toggle')[0])->toContain('Factura de manteca');
});

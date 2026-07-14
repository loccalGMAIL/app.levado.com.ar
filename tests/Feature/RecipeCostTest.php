<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForRecipeCost(): array
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

test('owner puede agregar ingrediente a una receta', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value]);

    $this->actingAs($user)
        ->post(route('recipes.ingredient-lines.store', $recipe), [
            'ingredient_id' => $ingredient->id,
            'quantity' => '500',
            'unit' => Unit::Gramo->value,
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->ingredientLines()->where('ingredient_id', $ingredient->id)->exists())->toBeTrue();
});

test('owner puede agregar ingrediente con unidad compatible diferente', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo->value]);

    $this->actingAs($user)
        ->post(route('recipes.ingredient-lines.store', $recipe), [
            'ingredient_id' => $ingredient->id,
            'quantity' => '500',
            'unit' => Unit::Gramo->value,
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->ingredientLines()->count())->toBe(1);
});

test('unidad incompatible es rechazada al agregar ingrediente', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo->value]);

    $this->actingAs($user)
        ->post(route('recipes.ingredient-lines.store', $recipe), [
            'ingredient_id' => $ingredient->id,
            'quantity' => '500',
            'unit' => Unit::Mililitro->value,
        ])
        ->assertSessionHasErrors(['unit']);

    expect($recipe->ingredientLines()->count())->toBe(0);
});

test('owner puede eliminar una línea de ingrediente', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value]);
    $line = RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 200,
        'unit' => Unit::Gramo->value,
    ]);

    $this->actingAs($user)
        ->delete(route('recipes.ingredient-lines.destroy', [$recipe, $line]))
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->ingredientLines()->count())->toBe(0);
});

test('owner puede actualizar cantidad y unidad de una línea de ingrediente a una unidad compatible', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => 'u']);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 1000,
    ]);
    $line = RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $response = $this->actingAs($user)
        ->patchJson(route('recipes.ingredient-lines.update', [$recipe, $line]), [
            'quantity' => '500',
            'unit' => Unit::Gramo->value,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    // 500 gr * $1000/kg = $0.5/gr * 500 = $500 → costPerLineUnit = $1/gr
    expect((float) $response->json('costPerLineUnit'))->toBe(1.0);

    $line->refresh();
    expect($line->unit)->toBe(Unit::Gramo)
        ->and((float) $line->quantity)->toBe(500.0)
        ->and((float) $recipe->fresh()->unit_cost)->toBe(500.0);
});

test('unidad incompatible es rechazada al actualizar línea de ingrediente', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo->value]);
    $line = RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $this->actingAs($user)
        ->patchJson(route('recipes.ingredient-lines.update', [$recipe, $line]), [
            'quantity' => '500',
            'unit' => Unit::Mililitro->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit');

    $line->refresh();
    expect($line->unit)->toBe(Unit::Kilogramo)
        ->and((float) $line->quantity)->toBe(1.0);
});

test('owner puede agregar envase a una receta', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $packaging = Packaging::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.packaging-lines.store', $recipe), [
            'packaging_id' => $packaging->id,
            'quantity' => '2',
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->packagingLines()->where('packaging_id', $packaging->id)->exists())->toBeTrue();
});

test('owner puede eliminar una línea de envase', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $packaging = Packaging::factory()->for($tenant)->create();
    $line = RecipePackagingLine::create([
        'recipe_id' => $recipe->id,
        'packaging_id' => $packaging->id,
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->delete(route('recipes.packaging-lines.destroy', [$recipe, $line]))
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->packagingLines()->count())->toBe(0);
});

test('owner puede agregar mano de obra a una receta', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $laborType = LaborType::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.labor-lines.store', $recipe), [
            'labor_type_id' => $laborType->id,
            'hours' => '2.5',
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->laborLines()->where('labor_type_id', $laborType->id)->exists())->toBeTrue();
});

test('owner puede eliminar una línea de mano de obra', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();
    $laborType = LaborType::factory()->for($tenant)->create();
    $line = RecipeLaborLine::create([
        'recipe_id' => $recipe->id,
        'labor_type_id' => $laborType->id,
        'hours' => 1.5,
    ]);

    $this->actingAs($user)
        ->delete(route('recipes.labor-lines.destroy', [$recipe, $line]))
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->laborLines()->count())->toBe(0);
});

test('costo total se calcula correctamente en la vista', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);

    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 1000,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
        'unit' => Unit::Gramo->value,
    ]);

    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 50]);
    RecipePackagingLine::create([
        'recipe_id' => $recipe->id,
        'packaging_id' => $packaging->id,
        'quantity' => 2,
    ]);

    $laborType = LaborType::factory()->for($tenant)->create(['hourly_rate' => 200]);
    RecipeLaborLine::create([
        'recipe_id' => $recipe->id,
        'labor_type_id' => $laborType->id,
        'hours' => 1,
    ]);

    // Ingredient cost: 0.5 kg * $1000/kg = $500  → costPerLineUnit = 1.0 $/gr
    // Packaging cost: 2 * $50 = $100
    // Labor cost: 1h * $200/h = $200
    // Total: $800, per unit: $80
    // @js() wraps in JSON.parse('...') and escapes " as "
    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('\u0022costPerLineUnit\u0022:1', false)
        ->assertSee('\u0022hourlyRate\u0022:200', false)
        ->assertSee('\u0022costPerUnit\u0022:50', false);
});

test('aislamiento: ingrediente de otro tenant no se puede agregar', function () {
    [$user, $tenant] = ownerForRecipeCost();
    $recipe = Recipe::factory()->for($tenant)->create();

    $otherTenant = Tenant::factory()->create();
    $foreignIngredient = Ingredient::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->post(route('recipes.ingredient-lines.store', $recipe), [
            'ingredient_id' => $foreignIngredient->id,
            'quantity' => '100',
            'unit' => Unit::Gramo->value,
        ])
        ->assertSessionHasErrors('ingredient_id');
});

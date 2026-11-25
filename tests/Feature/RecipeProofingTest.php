<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Models\Product;
use App\Models\Recipe;

function proofingRecipePayload(Recipe $recipe, array $overrides = []): array
{
    return array_merge([
        'name' => $recipe->name,
        'yield_quantity' => (float) $recipe->yield_quantity,
        'yield_unit' => $recipe->yield_unit->value,
        'is_semi_elaborate' => 0,
    ], $overrides);
}

test('owner marca en la receta que pasa por cámara de leudado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), proofingRecipePayload($recipe, ['uses_proofing' => 1, 'max_proofing_hours' => 18]))
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->uses_proofing)->toBeTrue()
        ->and($recipe->fresh()->max_proofing_hours)->toBe(18);
});

test('desmarcar la cámara borra las horas máximas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->proofing(12)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), proofingRecipePayload($recipe, ['uses_proofing' => 0, 'max_proofing_hours' => 12]))
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->uses_proofing)->toBeFalse()
        ->and($recipe->fresh()->max_proofing_hours)->toBeNull();
});

test('una receta nueva va directo por defecto', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();

    expect($recipe->fresh()->uses_proofing)->toBeFalse();
});

test('el artículo base y su presentación heredan el flag de la receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $base = doughProduct($tenant, 'Medialunas', proofing: true);
    $pack = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $base->recipe_id,
        'recipe_quantity' => 6,
        'cost_per_unit' => null,
    ]);
    $direct = doughProduct($tenant, 'Galletitas', proofing: false);

    expect($base->load('recipe')->usesProofing())->toBeTrue()
        ->and($pack->load('recipe')->usesProofing())->toBeTrue()
        ->and($direct->load('recipe')->usesProofing())->toBeFalse();
});

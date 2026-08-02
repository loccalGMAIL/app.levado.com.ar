<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\RecipeCostPropagator;
use Illuminate\Support\Facades\DB;

/**
 * El recálculo de costos recorre el árbol de recetas hacia arriba. Con cientos
 * de recetas eso era lo que dejaba colgada la pantalla de compras: cada renglón
 * imputado recorría el árbol entero por su cuenta.
 *
 * Estos tests fijan las dos garantías del recorrido nuevo: que una receta nunca
 * se recalcula antes que las sub-recetas de las que depende (orden topológico),
 * y que batch() agrupa toda una factura en un único recálculo.
 */
function tenantWithOwner(): array
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

function subrecipeLine(Recipe $parent, Recipe $child, float $quantity = 1): void
{
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => $quantity,
        'unit' => $child->yield_unit->value,
    ]);
}

function ingredientLine(Recipe $recipe, Ingredient $ingredient, float $quantity = 1): void
{
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => $quantity,
        'unit' => $ingredient->unit->value,
    ]);
}

test('una receta que usa dos sub-recetas del mismo insumo toma el costo nuevo de las dos', function () {
    // Diamante: el insumo está en A y en B, y la receta final usa A y B. Si el
    // padre se recalculara antes que alguna de las dos ramas, se quedaría con
    // el costo viejo de esa rama.
    $tenant = Tenant::factory()->create();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 1000,
    ]);

    $semiA = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $semiB = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Kilogramo->value,
    ]);
    $final = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
    ]);

    ingredientLine($semiA, $ingredient);
    ingredientLine($semiB, $ingredient);
    subrecipeLine($final, $semiA);
    subrecipeLine($final, $semiB);

    $propagator = app(RecipeCostPropagator::class);
    $propagator->propagateFromIngredient($ingredient->id);

    expect((float) $final->fresh()->unit_cost)->toBe(2000.0);

    $ingredient->update(['cost_per_unit' => 2000]);
    $propagator->propagateFromIngredient($ingredient->id);

    expect((float) $semiA->fresh()->unit_cost)->toBe(2000.0)
        ->and((float) $semiB->fresh()->unit_cost)->toBe(2000.0)
        ->and((float) $final->fresh()->unit_cost)->toBe(4000.0);
});

test('batch difiere el recálculo hasta el final y llega al mismo resultado', function () {
    $tenant = Tenant::factory()->create();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 100,
    ]);
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
        'unit_cost' => null,
    ]);
    ingredientLine($recipe, $ingredient);

    $propagator = app(RecipeCostPropagator::class);

    $propagator->batch(function () use ($propagator, $ingredient, $recipe) {
        $propagator->propagateFromIngredient($ingredient->id);

        // Todavía nada escrito: el recálculo espera al cierre del batch.
        expect($recipe->fresh()->unit_cost)->toBeNull();
    });

    expect((float) $recipe->fresh()->unit_cost)->toBe(100.0);
});

test('batch recalcula aunque el callback termine con excepción', function () {
    $tenant = Tenant::factory()->create();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 50,
    ]);
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
        'unit_cost' => null,
    ]);
    ingredientLine($recipe, $ingredient);

    $propagator = app(RecipeCostPropagator::class);

    expect(fn () => $propagator->batch(function () use ($propagator, $ingredient) {
        $propagator->propagateFromIngredient($ingredient->id);

        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect((float) $recipe->fresh()->unit_cost)->toBe(50.0);
});

test('ancestorsOf devuelve los ancestros transitivos y no cruza tenants', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $parent = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $grandparent = Recipe::factory()->for($tenant)->create(['yield_unit' => Unit::Kilogramo->value]);
    $unrelated = Recipe::factory()->for($tenant)->create();

    subrecipeLine($parent, $child);
    subrecipeLine($grandparent, $parent);

    $ancestors = app(RecipeCostPropagator::class)->ancestorsOf($child->id, $tenant->id);

    expect($ancestors)->toHaveCount(2)
        ->and($ancestors)->toContain($parent->id, $grandparent->id)
        ->and($ancestors)->not->toContain($unrelated->id);

    expect(app(RecipeCostPropagator::class)->ancestorsOf($child->id, $otherTenant->id))->toBe([]);
});

test('aplicar todas las sugerencias de una factura recalcula cada receta una sola vez', function () {
    [$user, $tenant] = tenantWithOwner();
    $supplier = Supplier::factory()->for($tenant)->create();

    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
    ]);

    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-08-01',
    ]);

    // Cinco insumos distintos, todos en la misma receta: antes cada renglón
    // disparaba su propio recorrido y la receta se escribía una vez por renglón.
    foreach (range(1, 5) as $n) {
        $ingredient = Ingredient::factory()->for($tenant)->create([
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => 10,
        ]);
        ingredientLine($recipe, $ingredient);

        $purchase->lines()->create([
            'raw_name' => "INSUMO {$n}",
            'purchaseable_type' => 'ingredient',
            'purchaseable_id' => $ingredient->id,
            'quantity_purchased' => 1,
            'purchase_unit' => Unit::Kilogramo->value,
            'unit_price' => 100 * $n,
            'subtotal' => 100 * $n,
        ]);
    }

    $recipeUpdates = 0;
    DB::listen(function ($query) use (&$recipeUpdates) {
        if (str_starts_with(strtolower(trim($query->sql)), 'update "recipes"')
            || str_starts_with(strtolower(trim($query->sql)), 'update `recipes`')) {
            $recipeUpdates++;
        }
    });

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect($recipeUpdates)->toBe(1)
        ->and($purchase->lines()->whereNull('cost_applied_at')->count())->toBe(0);

    // 1+2+3+4+5 = 15 kg de costo por unidad de rendimiento.
    expect((float) $recipe->fresh()->unit_cost)->toBe(1500.0);
});

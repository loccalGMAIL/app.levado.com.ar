<?php

use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeProductLine;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\RecipeCostPropagator;
use App\Services\StockService;
use Illuminate\Database\QueryException;

// tenantUserAs(), stockPurchaseFor(), stockLineFor(), lineRecorder(), manufacturedProduct(),
// seedStock() y productionService() son helpers globales (tests/Pest.php).

function chocolatada(Tenant $tenant, array $overrides = []): Product
{
    return Product::factory()->resale()->for($tenant)->create(array_merge([
        'name' => 'Leche chocolatada 1L',
        'unit' => Unit::Litro->value,
        'cost_per_unit' => 1000,
    ], $overrides));
}

function milkshakeRecipe(Tenant $tenant): Recipe
{
    return Recipe::factory()->for($tenant)->create([
        'name' => 'Milkshake',
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
    ]);
}

// --- Alta, edición y baja ---

test('owner agrega un artículo de reventa a una receta y el costo lo incluye', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $product = chocolatada($tenant);

    $this->actingAs($user)
        ->post(route('recipes.product-lines.store', $recipe), [
            'product_id' => $product->id,
            'quantity' => 250,
            'unit' => Unit::Mililitro->value,
        ])
        ->assertRedirect(route('recipes.show', $recipe));

    $recipe->refresh();
    $costs = app(RecipeCostCalculator::class)->calculate($recipe);

    expect($recipe->productLines()->count())->toBe(1)
        ->and($costs['ingredient_cost'])->toBe(250.0) // 0,25 L × $1000
        ->and((float) $recipe->unit_cost)->toBe(250.0);
});

test('el artículo de reventa y un ingrediente suman en el mismo costo de ingredientes', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 2]);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 50, 'unit' => Unit::Gramo->value]);
    $recipe->productLines()->create(['product_id' => chocolatada($tenant)->id, 'quantity' => 0.5, 'unit' => Unit::Litro->value]);

    $costs = app(RecipeCostCalculator::class)->calculate($recipe->fresh());

    expect($costs['ingredient_cost'])->toBe(600.0); // 50 g × $2 + 0,5 L × $1000
});

test('se rechaza un artículo elaborado, uno inactivo, uno de otro negocio y una unidad incompatible', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);

    $manufactured = Product::factory()->manufactured()->for($tenant)->create();
    $inactive = chocolatada($tenant, ['active' => false]);
    $foreign = chocolatada(Tenant::factory()->create());
    $valid = chocolatada($tenant);

    foreach ([$manufactured, $inactive, $foreign] as $invalid) {
        $this->actingAs($user)
            ->post(route('recipes.product-lines.store', $recipe), ['product_id' => $invalid->id, 'quantity' => 1, 'unit' => Unit::Litro->value])
            ->assertSessionHasErrors('product_id');
    }

    $this->actingAs($user)
        ->post(route('recipes.product-lines.store', $recipe), ['product_id' => $valid->id, 'quantity' => 1, 'unit' => Unit::Kilogramo->value])
        ->assertSessionHasErrors('unit');

    expect($recipe->productLines()->count())->toBe(0);
});

test('editar la cantidad de la línea recalcula el costo de la receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $line = $recipe->productLines()->create(['product_id' => chocolatada($tenant)->id, 'quantity' => 0.25, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)
        ->patchJson(route('recipes.product-lines.update', [$recipe, $line]), ['quantity' => 0.5, 'unit' => Unit::Litro->value])
        ->assertOk()
        ->assertJson(['ok' => true, 'costPerLineUnit' => 1000]);

    expect((float) $recipe->fresh()->unit_cost)->toBe(500.0);
});

test('quitar la línea recalcula el costo de la receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $line = $recipe->productLines()->create(['product_id' => chocolatada($tenant)->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);
    app(RecipeCostPropagator::class)->propagateFrom($recipe);

    $this->actingAs($user)
        ->delete(route('recipes.product-lines.destroy', [$recipe, $line]))
        ->assertRedirect();

    expect($recipe->productLines()->count())->toBe(0)
        ->and((float) $recipe->fresh()->unit_cost)->toBe(0.0);
});

test('una línea de otra receta resuelve 404', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $other = milkshakeRecipe($tenant);
    $line = $other->productLines()->create(['product_id' => chocolatada($tenant)->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)
        ->patchJson(route('recipes.product-lines.update', [$recipe, $line]), ['quantity' => 2, 'unit' => Unit::Litro->value])
        ->assertNotFound();
});

test('un viewer no puede agregar líneas de artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $recipe = milkshakeRecipe($tenant);

    $this->actingAs($user)
        ->post(route('recipes.product-lines.store', $recipe), ['product_id' => chocolatada($tenant)->id, 'quantity' => 1, 'unit' => Unit::Litro->value])
        ->assertForbidden();
});

// --- Propagación del costo del artículo hacia las recetas ---

test('comprar el artículo recalcula el costo de la receta y de sus recetas padre', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant, ['cost_per_unit' => 1000]);

    $sub = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $sub->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);
    $parent = milkshakeRecipe($tenant);
    $parent->subrecipeLines()->create(['child_recipe_id' => $sub->id, 'quantity_used' => 1, 'unit' => Unit::Unidad->value]);
    app(RecipeCostPropagator::class)->propagateFrom($sub);

    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product', 'purchaseable_id' => $product->id,
        'purchase_unit' => 'L', 'quantity_purchased' => 6, 'unit_price' => 1500, 'subtotal' => 9000,
    ]);
    lineRecorder()->apply($line);

    expect((float) $product->fresh()->cost_per_unit)->toBe(1500.0)
        ->and((float) $sub->fresh()->unit_cost)->toBe(1500.0)
        ->and((float) $parent->fresh()->unit_cost)->toBe(1500.0);
});

test('propagate false difiere la propagación de la compra del artículo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant, ['cost_per_unit' => 1000]);
    $recipe = milkshakeRecipe($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);
    app(RecipeCostPropagator::class)->propagateFrom($recipe);

    $line = stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'product', 'purchaseable_id' => $product->id,
        'purchase_unit' => 'L', 'quantity_purchased' => 2, 'unit_price' => 2000, 'subtotal' => 4000,
    ]);
    lineRecorder()->apply($line, propagate: false);

    expect((float) $recipe->fresh()->unit_cost)->toBe(1000.0); // sigue con el costo viejo hasta propagar

    app(RecipeCostPropagator::class)->propagateManyFrom(
        app(RecipeCostPropagator::class)->recipeIdsUsingProducts([$product->id]),
    );

    expect((float) $recipe->fresh()->unit_cost)->toBe(2000.0);
});

test('editar a mano el costo del artículo de reventa propaga a las recetas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant, ['cost_per_unit' => 1000]);
    $recipe = milkshakeRecipe($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 0.5, 'unit' => Unit::Litro->value]);
    app(RecipeCostPropagator::class)->propagateFrom($recipe);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'resale',
            'unit' => Unit::Litro->value,
            'cost_per_unit' => 1400,
        ]);

    expect((float) $product->fresh()->cost_per_unit)->toBe(1400.0)
        ->and((float) $recipe->fresh()->unit_cost)->toBe(700.0);
});

// --- Producción ---

test('producir descuenta el stock del artículo de reventa y lo costea como material', function () {
    [$user, $tenant] = stockTenantUser();
    $product = chocolatada($tenant, ['cost_per_unit' => 1000]);
    $recipe = milkshakeRecipe($tenant);
    $recipe->yield_quantity = 2;
    $recipe->save();
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 500, 'unit' => Unit::Mililitro->value]);
    $milkshake = manufacturedProduct($tenant, $recipe);
    seedStock($product, 10, $user);

    $production = productionService()->produce($milkshake, 4, null, $user); // factor 2 → 1 L

    $stock = app(StockService::class);
    $location = $tenant->defaultLocation();

    expect((float) $stock->levelFor($product, $location)->quantity)->toBe(9.0)
        ->and((float) $stock->levelFor($milkshake, $location)->quantity)->toBe(4.0)
        ->and((float) $production->material_cost)->toBe(1000.0);

    $movement = $product->stockMovements()->where('type', StockMovementType::Production->value)->sole();
    expect((float) $movement->quantity)->toBe(-1.0);
});

test('el preview de producción muestra el artículo con su propia unidad', function () {
    [, $tenant] = stockTenantUser();
    $product = chocolatada($tenant);
    $recipe = milkshakeRecipe($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => Unit::Litro->value]);
    $milkshake = manufacturedProduct($tenant, $recipe);

    $preview = productionService()->preview($milkshake, 1, $tenant->defaultLocation());

    expect($preview['lines'])->toHaveCount(1)
        ->and($preview['lines'][0]['name'])->toBe($product->name)
        ->and($preview['lines'][0]['unit'])->toBe('L')
        ->and($preview['lines'][0]['quantity'])->toBe(0.25);
});

// --- Guardas ---

test('no se puede desactivar un artículo usado en una receta activa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant);
    $recipe = milkshakeRecipe($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)
        ->patch(route('products.toggle-active', $product))
        ->assertSessionHasErrors('toggle');

    expect($product->fresh()->active)->toBeTrue();
});

test('un artículo usado solo en recetas inactivas sí se puede desactivar', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant);
    $recipe = milkshakeRecipe($tenant);
    $recipe->update(['active' => false]);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)->patch(route('products.toggle-active', $product));

    expect($product->fresh()->active)->toBeFalse();
});

test('no se puede pasar a elaborado un artículo usado como insumo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant);
    $recipe = milkshakeRecipe($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);
    $other = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => 'manufactured',
            'unit' => Unit::Litro->value,
            'recipe_id' => $other->id,
        ])
        ->assertSessionHasErrors('type');

    expect($product->fresh()->isResale())->toBeTrue();
});

test('el artículo usado en recetas no se puede borrar de la base (restrict)', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = chocolatada($tenant);
    milkshakeRecipe($tenant)->productLines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    expect(fn () => $product->delete())->toThrow(QueryException::class);
});

// --- Vistas y copia ---

test('la ficha de la receta lista el artículo junto a los ingredientes con la marca Reventa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $product = chocolatada($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee('Reventa')
        ->assertSee('ART-'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT));
});

test('copiar la receta copia también las líneas de artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = milkshakeRecipe($tenant);
    $product = chocolatada($tenant);
    $recipe->productLines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => Unit::Litro->value]);

    $this->actingAs($user)->post(route('recipes.copy', $recipe));

    $copy = Recipe::where('tenant_id', $tenant->id)->where('id', '!=', $recipe->id)->sole();
    $line = RecipeProductLine::where('recipe_id', $copy->id)->sole();

    expect($line->product_id)->toBe($product->id)
        ->and((float) $line->quantity)->toBe(0.25)
        ->and((float) $copy->unit_cost)->toBe(250.0);
});

// --- Comando de unificación ---

test('catalog:unify-ingredient --list detecta el par con el mismo nombre', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->create(['name' => 'Leche Chocolatada 1L', 'unit' => Unit::Litro->value]);
    chocolatada($tenant, ['name' => 'leche chocolatada  1l']);
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000']);

    $this->artisan('catalog:unify-ingredient', ['--list' => true])
        ->expectsOutputToContain('1 par(es)')
        ->assertSuccessful();
});

test('catalog:unify-ingredient --dry-run no escribe nada y sin --dry-run unifica', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Litro->value]);
    $product = chocolatada($tenant);
    $recipe = milkshakeRecipe($tenant);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    $this->artisan('catalog:unify-ingredient', ['ingredient' => $ingredient->id, 'product' => $product->id, '--dry-run' => true])
        ->assertSuccessful();

    expect($ingredient->fresh()->active)->toBeTrue()
        ->and($recipe->ingredientLines()->count())->toBe(1);

    $this->artisan('catalog:unify-ingredient', ['ingredient' => $ingredient->id, 'product' => $product->id, '--force' => true])
        ->assertSuccessful();

    expect($ingredient->fresh()->converted_to_product_id)->toBe($product->id)
        ->and($recipe->productLines()->count())->toBe(1)
        ->and($recipe->ingredientLines()->count())->toBe(0);
});

test('catalog:unify-ingredient falla con unidades distintas', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo->value]);
    $product = chocolatada($tenant);

    $this->artisan('catalog:unify-ingredient', ['ingredient' => $ingredient->id, 'product' => $product->id, '--force' => true])
        ->assertFailed();

    expect($ingredient->fresh()->active)->toBeTrue();
});

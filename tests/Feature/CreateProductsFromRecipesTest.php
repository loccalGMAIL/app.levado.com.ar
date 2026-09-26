<?php

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\Tenant;

/** Receta final con un precio de venta cargado en la lista default del tenant. */
function pricedRecipe(Tenant $tenant, array $attributes = []): Recipe
{
    $recipe = Recipe::factory()->for($tenant)->create($attributes);
    $recipe->prices()->create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $tenant->defaultPriceList()->id,
        'price' => 1000,
    ]);

    return $recipe;
}

test('crea un producto elaborado para una receta con precio', function () {
    $tenant = Tenant::factory()->create();
    $recipe = pricedRecipe($tenant, ['name' => 'Pan de campo', 'yield_quantity' => 1, 'yield_unit' => Unit::Kilogramo->value]);

    $this->artisan('products:from-recipes', ['--force' => true])->assertExitCode(0);

    $product = Product::where('recipe_id', $recipe->id)->first();
    expect($product)->not->toBeNull()
        ->and($product->type)->toBe(ProductType::Manufactured)
        ->and($product->tenant_id)->toBe($tenant->id)
        ->and($product->name)->toBe('Pan de campo')
        ->and($product->unit)->toBe(Unit::Kilogramo)
        ->and($product->cost_per_unit)->toBeNull()
        ->and($product->active)->toBeTrue();
});

test('saltea las recetas semielaboradas', function () {
    $tenant = Tenant::factory()->create();
    $semi = pricedRecipe($tenant, ['is_semi_elaborate' => true]);

    $this->artisan('products:from-recipes', ['--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $semi->id)->exists())->toBeFalse();
});

test('saltea las recetas sin precio por defecto', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->artisan('products:from-recipes', ['--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

test('con --all incluye las recetas sin precio', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->artisan('products:from-recipes', ['--all' => true, '--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $recipe->id)->exists())->toBeTrue();
});

test('saltea las recetas que ya tienen un producto elaborado', function () {
    $tenant = Tenant::factory()->create();
    $recipe = pricedRecipe($tenant);
    Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);

    $this->artisan('products:from-recipes', ['--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $recipe->id)->count())->toBe(1);
});

test('dry-run no crea ningún producto', function () {
    $tenant = Tenant::factory()->create();
    $recipe = pricedRecipe($tenant);

    $this->artisan('products:from-recipes', ['--dry-run' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

test('con --tenant limita a un negocio', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $ra = pricedRecipe($a);
    $rb = pricedRecipe($b);

    $this->artisan('products:from-recipes', ['--tenant' => $a->id, '--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $ra->id)->exists())->toBeTrue()
        ->and(Product::where('recipe_id', $rb->id)->exists())->toBeFalse();
});

test('saltea las recetas inactivas', function () {
    $tenant = Tenant::factory()->create();
    $recipe = pricedRecipe($tenant, ['active' => false]);

    $this->artisan('products:from-recipes', ['--force' => true])->assertExitCode(0);

    expect(Product::where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

test('con --category asigna la categoría (producible) a los productos creados', function () {
    $tenant = Tenant::factory()->create();
    $recipe = pricedRecipe($tenant);

    $this->artisan('products:from-recipes', ['--category' => 'Panadería', '--force' => true])->assertExitCode(0);

    $product = Product::where('recipe_id', $recipe->id)->first();
    $category = ProductCategory::find($product->product_category_id);

    expect($category)->not->toBeNull()
        ->and($category->name)->toBe('Panadería')
        ->and($category->tenant_id)->toBe($tenant->id)
        ->and($category->producible)->toBeTrue();
});

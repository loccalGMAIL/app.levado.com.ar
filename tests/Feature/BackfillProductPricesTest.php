<?php

use App\Enums\PricingPolicy;
use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Models\Tenant;

/** Receta final con precio en la lista default + su producto elaborado (sin product_price). */
function recipeWithProduct(Tenant $tenant, float $price = 1000, array $attributes = []): array
{
    $recipe = Recipe::factory()->for($tenant)->create($attributes);
    $recipe->prices()->create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $tenant->defaultPriceList()->id,
        'price' => $price,
    ]);
    $product = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);

    return [$recipe, $product];
}

test('copia el precio de la receta al artículo elaborado como política manual', function () {
    $tenant = Tenant::factory()->create();
    [$recipe, $product] = recipeWithProduct($tenant, 1500);

    $this->artisan('products:backfill-prices')->assertExitCode(0);

    $pp = ProductPrice::where('product_id', $product->id)
        ->where('price_list_id', $tenant->defaultPriceList()->id)
        ->first();

    expect($pp)->not->toBeNull()
        ->and((float) $pp->price)->toBe(1500.0)
        ->and($pp->policy_type)->toBe(PricingPolicy::Manual)
        ->and($pp->policy_value)->toBeNull()
        ->and($pp->tenant_id)->toBe($tenant->id);
});

test('es idempotente: correrlo dos veces no duplica ni pisa', function () {
    $tenant = Tenant::factory()->create();
    [$recipe, $product] = recipeWithProduct($tenant, 1000);

    $this->artisan('products:backfill-prices')->assertExitCode(0);
    $this->artisan('products:backfill-prices')->assertExitCode(0);

    expect(ProductPrice::where('product_id', $product->id)->count())->toBe(1);
});

test('no pisa un product_price ya existente (respeta su política)', function () {
    $tenant = Tenant::factory()->create();
    [$recipe, $product] = recipeWithProduct($tenant, 1000);

    // El artículo ya tiene precio con política margen: no debe tocarse.
    ProductPrice::create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $tenant->defaultPriceList()->id,
        'product_id' => $product->id,
        'price' => 2000,
        'policy_type' => PricingPolicy::Margin->value,
        'policy_value' => 40,
    ]);

    $this->artisan('products:backfill-prices')->assertExitCode(0);

    $pp = ProductPrice::where('product_id', $product->id)->sole();
    expect((float) $pp->price)->toBe(2000.0)
        ->and($pp->policy_type)->toBe(PricingPolicy::Margin)
        ->and((float) $pp->policy_value)->toBe(40.0);
});

test('ignora recetas sin producto elaborado', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();
    $recipe->prices()->create([
        'tenant_id' => $tenant->id,
        'price_list_id' => $tenant->defaultPriceList()->id,
        'price' => 900,
    ]);

    $this->artisan('products:backfill-prices')->assertExitCode(0);

    expect(ProductPrice::count())->toBe(0);
});

test('con --tenant limita a un negocio', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    [, $productA] = recipeWithProduct($a, 1000);
    [, $productB] = recipeWithProduct($b, 1000);

    $this->artisan('products:backfill-prices', ['--tenant' => $a->id])->assertExitCode(0);

    expect(ProductPrice::where('product_id', $productA->id)->exists())->toBeTrue()
        ->and(ProductPrice::where('product_id', $productB->id)->exists())->toBeFalse();
});

test('dry-run no escribe nada', function () {
    $tenant = Tenant::factory()->create();
    [, $product] = recipeWithProduct($tenant, 1000);

    $this->artisan('products:backfill-prices', ['--dry-run' => true])->assertExitCode(0);

    expect(ProductPrice::where('product_id', $product->id)->exists())->toBeFalse();
});

<?php

use App\Enums\ProductionStatus;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Production;
use App\Models\Recipe;
use App\Services\ProductionService;
use App\Services\StockService;

// tenantUserAs() es global (IngredientCrudTest); manufacturedProduct() y seedStock() son globales (ProductionTest).

/** Arma un elaborado con receta (1 ingrediente) en una categoría producible, para un tenant nuevo. */
function productionSetup(TenantUserRole $role = TenantUserRole::Owner): array
{
    [$user, $tenant] = tenantUserAs($role);
    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $category = $tenant->productCategories()->create(['name' => 'Producción', 'producible' => true]);
    $product->update(['product_category_id' => $category->id]);

    return [$user, $tenant, $product, $harina];
}

test('el índice de producción se renderiza', function () {
    [$user] = productionSetup();

    $this->actingAs($user)->get(route('production.index'))->assertOk()->assertSee('Producción');
});

test('la pantalla de producir lista los elaborados con receta', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)->get(route('production.create'))->assertOk()->assertSee($product->name);
});

test('el endpoint de preview devuelve el consumo de insumos en JSON', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->postJson(route('production.preview'), ['product_id' => $product->id, 'quantity' => 24])
        ->assertOk()
        ->assertJsonStructure(['lines' => [['type', 'id', 'name', 'unit', 'quantity', 'available', 'shortfall', 'unit_cost', 'line_cost']], 'total_cost'])
        ->assertJsonPath('lines.0.name', 'Harina');
});

test('producir por el controller registra la producción y descuenta stock', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);

    $this->actingAs($user)
        ->post(route('production.store'), ['product_id' => $product->id, 'quantity' => 24])
        ->assertRedirect();

    $production = Production::where('tenant_id', $tenant->id)->first();
    $stock = app(StockService::class);
    $loc = $tenant->defaultLocation();

    expect($production)->not->toBeNull()
        ->and((float) $stock->levelFor($harina->fresh(), $loc)->quantity)->toBe(4000.0)
        ->and((float) $stock->levelFor($product->fresh(), $loc)->quantity)->toBe(24.0);
});

test('anular por el controller revierte el stock y marca la producción', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);

    $this->actingAs($user)->post(route('production.store'), ['product_id' => $product->id, 'quantity' => 24]);
    $production = Production::where('tenant_id', $tenant->id)->firstOrFail();

    $this->actingAs($user)->patch(route('production.cancel', $production))->assertRedirect();

    expect($production->fresh()->status)->toBe(ProductionStatus::Cancelled)
        ->and((float) app(StockService::class)->levelFor($harina->fresh(), $tenant->defaultLocation())->quantity)->toBe(5000.0);
});

test('un viewer no puede abrir la pantalla de producir', function () {
    [$user] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)->get(route('production.create'))->assertForbidden();
});

test('la validación rechaza producir un producto de reventa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $resale = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->post(route('production.store'), ['product_id' => $resale->id, 'quantity' => 5])
        ->assertSessionHasErrors('product_id');
});

test('no se puede ver la producción de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    [$other, , $otherProduct, $otherHarina] = productionSetup();
    seedStock($otherHarina, 5000, $other);
    $production = app(ProductionService::class)->produce($otherProduct, 12, null, $other);

    $this->actingAs($user)->get(route('production.show', $production))->assertNotFound();
});

// --- Filtro por categoría "se produce" en el select de producir ---

test('el select de producir muestra el elaborado de una categoría que se produce', function () {
    [$user, $tenant, $product] = productionSetup(); // ya viene en categoría producible

    $this->actingAs($user)->get(route('production.create'))->assertOk()->assertSee($product->name);
});

test('el select de producir oculta un elaborado sin categoría', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $product->update(['name' => 'ElaboradoSinCategoriaZZ']); // sin categoría

    $this->actingAs($user)->get(route('production.create'))->assertOk()->assertDontSee('ElaboradoSinCategoriaZZ');
});

test('el select de producir oculta un elaborado de una categoría que no se produce', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $cafeteria = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false]);
    $product->update(['name' => 'ElaboradoCafeteriaZZ', 'product_category_id' => $cafeteria->id]);

    $this->actingAs($user)->get(route('production.create'))->assertOk()->assertDontSee('ElaboradoCafeteriaZZ');
});

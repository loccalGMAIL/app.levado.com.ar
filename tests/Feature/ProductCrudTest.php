<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

// --- Listado ---

test('owner puede ver la lista de artículos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Product::factory()->for($tenant)->resale()->create(['name' => 'Gaseosa 500ml']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Gaseosa 500ml');
});

test('viewer puede ver la lista de artículos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    Product::factory()->for($tenant)->resale()->create(['name' => 'Agua mineral']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Agua mineral');
});

// --- Crear reventa ---

test('owner puede crear un producto de reventa con costo propio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Chicle',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '120.50',
            'barcode' => '7790001234567',
        ])
        ->assertRedirect(route('products.index'));

    $product = $tenant->products()->where('name', 'Chicle')->first();

    expect($product)->not->toBeNull()
        ->and($product->type)->toBe(ProductType::Resale)
        ->and($product->recipe_id)->toBeNull()
        ->and((float) $product->cost_per_unit)->toBe(120.5)
        ->and($product->barcode)->toBe('7790001234567');
});

test('crear un producto de reventa sin costo falla', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Sin costo',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors('cost_per_unit');
});

// --- Crear elaborado ---

test('owner puede crear un producto elaborado ligado a una receta, sin costo propio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan francés']);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Pan francés',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'unit' => Unit::Unidad->value,
            // Aunque mande costo, el elaborado lo ignora (se deriva de la receta).
            'cost_per_unit' => '999',
        ])
        ->assertRedirect(route('products.index'));

    $product = $tenant->products()->where('name', 'Pan francés')->first();

    expect($product)->not->toBeNull()
        ->and($product->type)->toBe(ProductType::Manufactured)
        ->and($product->recipe_id)->toBe($recipe->id)
        ->and($product->cost_per_unit)->toBeNull();
});

test('crear un producto elaborado sin receta falla', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Elaborado sin receta',
            'type' => ProductType::Manufactured->value,
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors('recipe_id');
});

// --- Validaciones ---

test('nombre y tipo son obligatorios', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors(['name', 'type']);
});

test('el código de barras es único por tenant', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Product::factory()->for($tenant)->resale()->create(['barcode' => '7790000000001']);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Duplicado',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '100',
            'barcode' => '7790000000001',
        ])
        ->assertSessionHasErrors('barcode');
});

test('el mismo código de barras puede existir en otro tenant', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    Product::factory()->for($otherTenant)->resale()->create(['barcode' => '7790000000009']);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Mismo código, otro negocio',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '100',
            'barcode' => '7790000000009',
        ])
        ->assertRedirect(route('products.index'));

    expect($tenant->products()->where('barcode', '7790000000009')->exists())->toBeTrue();
});

test('viewer no puede crear artículos', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Prohibido',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '100',
        ])
        ->assertForbidden();
});

// --- Editar ---

test('cambiar un elaborado a reventa nulea la receta y toma el costo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();
    $product = Product::factory()->for($tenant)->manufactured()->create(['recipe_id' => $recipe->id]);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'type' => ProductType::Resale->value,
            'unit' => $product->unit->value,
            'cost_per_unit' => '350',
        ])
        ->assertRedirect(route('products.index'));

    $product->refresh();

    expect($product->type)->toBe(ProductType::Resale)
        ->and($product->recipe_id)->toBeNull()
        ->and((float) $product->cost_per_unit)->toBe(350.0);
});

test('el select de edición lista recetas inactivas para no perder la asociación', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Receta Baja', 'active' => false]);
    Product::factory()->for($tenant)->manufactured()->create(['recipe_id' => $recipe->id]);

    $html = $this->actingAs($user)->get(route('products.index'))->assertOk()->getContent();
    $editSelect = str($html)->after('id="edit_product_recipe"')->before('</select>')->toString();

    expect($editSelect)->toContain('Receta Baja')
        ->and($editSelect)->toContain('(inactiva)')
        ->and($editSelect)->toContain('value="'.$recipe->id.'"');
});

// --- Toggle ---

test('owner puede desactivar un artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('products.toggle-active', $product))
        ->assertRedirect();

    expect($product->fresh()->active)->toBeFalse();
});

// --- Aislamiento ---

test('aislamiento: no se puede crear un elaborado con receta de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    $foreignRecipe = Recipe::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Receta ajena',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $foreignRecipe->id,
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors('recipe_id');
});

test('aislamiento: no se puede editar un artículo de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    $foreign = Product::factory()->for($otherTenant)->resale()->create();

    $this->actingAs($user)
        ->put(route('products.update', $foreign), [
            'name' => 'Hack',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '1',
        ])
        ->assertNotFound();
});

test('aislamiento: artículos de otro tenant no aparecen en el listado', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    Product::factory()->for($otherTenant)->resale()->create(['name' => 'AjenoProducto']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertDontSee('AjenoProducto');
});

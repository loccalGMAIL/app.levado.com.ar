<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

test('owner puede crear una categoría con su flag se produce', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('product-categories.store'), ['name' => 'Panadería', 'producible' => '1'])
        ->assertRedirect();

    $category = $tenant->productCategories()->where('name', 'Panadería')->first();
    expect($category)->not->toBeNull()
        ->and($category->producible)->toBeTrue();
});

test('owner puede crear una categoría marcada no se produce', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('product-categories.store'), ['name' => 'Cafetería', 'producible' => '0'])
        ->assertRedirect();

    expect($tenant->productCategories()->where('name', 'Cafetería')->first()->producible)->toBeFalse();
});

test('el alta rápida por JSON crea la categoría producible por defecto', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->postJson(route('product-categories.store'), ['name' => 'Pastelería'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name']);

    expect($tenant->productCategories()->where('name', 'Pastelería')->first()->producible)->toBeTrue();
});

test('owner puede renombrar y togglear el flag de una categoría', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => true]);

    $this->actingAs($user)
        ->put(route('product-categories.update', $category), ['name' => 'Cafetería y bar', 'producible' => '0'])
        ->assertRedirect();

    $category->refresh();
    expect($category->name)->toBe('Cafetería y bar')
        ->and($category->producible)->toBeFalse();
});

test('owner puede eliminar una categoría sin artículos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Temporal', 'producible' => true]);

    $this->actingAs($user)
        ->delete(route('product-categories.destroy', $category))
        ->assertRedirect();

    expect(ProductCategory::find($category->id))->toBeNull();
});

test('no se puede eliminar una categoría con artículos asignados', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false]);
    Product::factory()->for($tenant)->resale()->create(['product_category_id' => $category->id]);

    $this->actingAs($user)
        ->delete(route('product-categories.destroy', $category))
        ->assertRedirect();

    expect(ProductCategory::find($category->id))->not->toBeNull();
});

test('no se puede repetir el nombre de una categoría en el mismo negocio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->productCategories()->create(['name' => 'Panadería', 'producible' => true]);

    $this->actingAs($user)
        ->post(route('product-categories.store'), ['name' => 'Panadería'])
        ->assertSessionHasErrors('name');

    expect($tenant->productCategories()->where('name', 'Panadería')->count())->toBe(1);
});

test('aislamiento: no se puede eliminar una categoría de otro negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $other = Tenant::factory()->create();
    $foreign = $other->productCategories()->create(['name' => 'Ajena', 'producible' => true]);

    $this->actingAs($user)
        ->delete(route('product-categories.destroy', $foreign))
        ->assertNotFound();
});

test('se puede asignar una categoría a un producto al crearlo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false]);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Café con leche',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '300',
            'product_category_id' => $category->id,
        ])
        ->assertRedirect();

    expect($tenant->products()->where('name', 'Café con leche')->first()->product_category_id)->toBe($category->id);
});

test('no se puede asignar una categoría de otro negocio a un producto', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $other = Tenant::factory()->create();
    $foreign = $other->productCategories()->create(['name' => 'Ajena', 'producible' => true]);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Producto',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '100',
            'product_category_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('product_category_id');
});

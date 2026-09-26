<?php

use App\Enums\TenantUserRole;
use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\ProductCategory;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

test('owner asigna una categoría a varios artículos de una', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Panificados', 'producible' => true]);
    $products = Product::factory()->for($tenant)->resale()->count(3)->create();

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), [
            'product_ids' => $products->pluck('id')->all(),
            'product_category_id' => $category->id,
        ])
        ->assertRedirect();

    foreach ($products as $product) {
        expect($product->fresh()->product_category_id)->toBe($category->id);
    }
    expect(AdminAuditLog::where('action', 'product.category_assigned')->count())->toBe(3);
});

test('asignar con categoría vacía quita la categoría', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Panificados', 'producible' => true]);
    $product = Product::factory()->for($tenant)->resale()->create(['product_category_id' => $category->id]);

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), [
            'product_ids' => [$product->id],
            'product_category_id' => '',
        ])
        ->assertRedirect();

    expect($product->fresh()->product_category_id)->toBeNull();
});

test('un artículo de otro negocio no se toca', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [, $otherTenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Panificados', 'producible' => true]);
    $ownProduct = Product::factory()->for($tenant)->resale()->create();
    $foreignProduct = Product::factory()->for($otherTenant)->resale()->create();

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), [
            'product_ids' => [$ownProduct->id, $foreignProduct->id],
            'product_category_id' => $category->id,
        ])
        ->assertRedirect();

    expect($ownProduct->fresh()->product_category_id)->toBe($category->id)
        ->and($foreignProduct->fresh()->product_category_id)->toBeNull();
});

test('no se puede asignar una categoría de otro negocio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [, $otherTenant] = tenantUserAs(TenantUserRole::Owner);
    $foreignCategory = ProductCategory::factory()->for($otherTenant)->create();
    $product = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), [
            'product_ids' => [$product->id],
            'product_category_id' => $foreignCategory->id,
        ])
        ->assertSessionHasErrors('product_category_id');

    expect($product->fresh()->product_category_id)->toBeNull();
});

test('product_ids es obligatorio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $category = $tenant->productCategories()->create(['name' => 'Panificados', 'producible' => true]);

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), ['product_category_id' => $category->id])
        ->assertSessionHasErrors('product_ids');
});

test('viewer no puede asignar categorías masivamente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $category = $tenant->productCategories()->create(['name' => 'Panificados', 'producible' => true]);
    $product = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->patch(route('products.bulk-category'), [
            'product_ids' => [$product->id],
            'product_category_id' => $category->id,
        ])
        ->assertForbidden();
});

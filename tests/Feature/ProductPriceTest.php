<?php

use App\Enums\TenantUserRole;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Tenant;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

// --- Matriz ---

test('la matriz de reventa lista productos de reventa y no elaborados', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Product::factory()->for($tenant)->resale()->create(['name' => 'GaseosaReventa']);
    Product::factory()->for($tenant)->manufactured()->create(['name' => 'PanElaborado']);

    $this->actingAs($user)
        ->get(route('products.prices.matrix'))
        ->assertOk()
        ->assertSee('GaseosaReventa')
        ->assertDontSee('PanElaborado');
});

// --- Setear precio ---

test('PATCH crea el precio y devuelve margen con color', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 100])
        ->assertOk()
        ->assertJson([
            'selling_price' => 100,
            'margin' => 50,
            'margin_pct' => 50,
            'margin_pct_formatted' => '50,0',
            'margin_color' => 'text-green-600',
        ]);

    expect((float) $product->prices()->where('price_list_id', $list->id)->value('price'))->toBe(100.0);
});

test('PATCH actualiza un precio existente y null lo elimina', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 120])
        ->assertOk()
        ->assertJson(['selling_price' => 120]);

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => null])
        ->assertOk()
        ->assertJson(['selling_price' => null, 'margin' => null]);

    expect($product->prices()->where('price_list_id', $list->id)->exists())->toBeFalse();
});

test('el margen usa el semáforo de colores', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $list = PriceList::factory()->for($tenant)->create();

    // margen 20% → ámbar
    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 62.50])
        ->assertJson(['margin_color' => 'text-amber-600']);

    // margen 9% → rojo
    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 55])
        ->assertJson(['margin_color' => 'text-red-500']);
});

test('loguea al crear y al cambiar, no si el monto no cambió', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $list = PriceList::factory()->for($tenant)->create();
    $url = route('products.prices.update', [$product, $list]);

    $this->actingAs($user)->patchJson($url, ['price' => 100]);
    $this->actingAs($user)->patchJson($url, ['price' => 100]);
    expect($product->priceLogs()->count())->toBe(1);

    $this->actingAs($user)->patchJson($url, ['price' => 110]);
    expect($product->priceLogs()->count())->toBe(2);
});

// --- Guards ---

test('no se puede precian un producto elaborado en la matriz de reventa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->manufactured()->create();
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 100])
        ->assertStatus(422);
});

test('viewer no puede setear precios de reventa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $product = Product::factory()->for($tenant)->resale()->create();
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 100])
        ->assertForbidden();
});

test('una lista inactiva devuelve 422', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create();
    $list = PriceList::factory()->for($tenant)->create(['active' => false]);

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 100])
        ->assertStatus(422);
});

// --- Aislamiento ---

test('aislamiento: producto o lista de otro tenant devuelve 404', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $other = Tenant::factory()->create();

    $foreignProduct = Product::factory()->for($other)->resale()->create();
    $ownList = PriceList::factory()->for($tenant)->create();
    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$foreignProduct, $ownList]), ['price' => 100])
        ->assertNotFound();

    $ownProduct = Product::factory()->for($tenant)->resale()->create();
    $foreignList = PriceList::factory()->for($other)->create();
    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$ownProduct, $foreignList]), ['price' => 100])
        ->assertNotFound();
});

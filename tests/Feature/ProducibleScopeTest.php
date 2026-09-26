<?php

use App\Enums\TenantUserRole;
use App\Models\Product;
use App\Models\Tenant;

// tenantUserAs() es global (IngredientCrudTest).

/** Arma un candidato del gate producible según el caso del dataset. */
function producibleCandidate(Tenant $tenant, string $case): Product
{
    $producibleCategory = fn () => $tenant->productCategories()->create(['name' => 'Producción', 'producible' => true]);

    return match ($case) {
        'producible' => Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $producibleCategory()->id]),
        'no_producible' => Product::factory()->for($tenant)->manufactured()->create([
            'product_category_id' => $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false])->id,
        ]),
        'inactivo' => Product::factory()->for($tenant)->manufactured()->inactive()->create(),
        'sin_receta' => Product::factory()->for($tenant)->create(['recipe_id' => null]),
        'reventa' => Product::factory()->for($tenant)->resale()->create(['product_category_id' => $producibleCategory()->id]),
        default => Product::factory()->for($tenant)->manufactured()->create(), // sin_categoria
    };
}

test('el gate producible incluye o excluye según el artículo', function (string $case, bool $expected) {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = producibleCandidate($tenant, $case);

    $found = $tenant->products()->producible()->whereKey($product->id)->exists();

    expect($found)->toBe($expected);
})->with([
    'elaborado sin categoría' => ['sin_categoria', true],
    'elaborado en categoría que se produce' => ['producible', true],
    'elaborado en categoría que no se produce' => ['no_producible', false],
    'elaborado inactivo sin categoría' => ['inactivo', false],
    'elaborado sin receta' => ['sin_receta', false],
    // Ancla el agrupamiento del OR: sin el where(closure) que lo agrupa, este
    // caso se cuela porque el orWhereHas se aplicaría contra toda la cadena.
    'reventa en categoría que se produce' => ['reventa', false],
]);

test('el gate producible no cruza artículos de otro negocio', function () {
    [, $tenantA] = tenantUserAs(TenantUserRole::Owner);
    [, $tenantB] = tenantUserAs(TenantUserRole::Owner);
    producibleCandidate($tenantB, 'sin_categoria');

    expect($tenantA->products()->producible()->count())->toBe(0);
});

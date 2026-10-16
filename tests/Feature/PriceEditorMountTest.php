<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/**
 * El popover de edición de precio se monta una sola vez en el layout; las celdas
 * priceCell solo muestran y le piden editar con un evento. Antes cada celda traía
 * su propio popover teletransportado (decenas por página).
 */
function priceEditorUser(TenantUserRole $role = TenantUserRole::Owner): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function priceEditorRecipes(Tenant $tenant, int $count): void
{
    foreach (range(1, $count) as $i) {
        $recipe = Recipe::factory()->for($tenant)->create(['name' => "Receta {$i}"]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'unit' => $recipe->yield_unit->value,
        ]);
    }
}

dataset('price editor pages', [
    'recetas' => ['recipes.index'],
    'artículos' => ['products.index'],
    'matriz' => ['products.matrix'],
    'dashboard' => ['dashboard'],
]);

test('el editor de precio se monta una sola vez por página', function (string $routeName) {
    [$user, $tenant] = priceEditorUser();
    priceEditorRecipes($tenant, 5);

    $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

    expect(substr_count($html, 'x-data="priceEditor"'))->toBe(1)
        ->and($html)->not->toContain('x-teleport');
})->with('price editor pages');

test('un viewer no recibe el editor de precio', function () {
    [$user, $tenant] = priceEditorUser(TenantUserRole::Viewer);
    priceEditorRecipes($tenant, 3);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertDontSee('priceEditor');
});

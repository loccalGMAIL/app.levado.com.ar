<?php

use App\Enums\CatalogItemType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePackagingLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\CatalogItemReplacer;
use Symfony\Component\HttpKernel\Exception\HttpException;

function ownerForCatalogReplacement(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function catalogReplacer(): CatalogItemReplacer
{
    return app(CatalogItemReplacer::class);
}

// --- Ingredientes ---

test('reemplazar un ingrediente en varias recetas conserva cantidad y unidad de cada línea', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $recipeA = Recipe::factory()->for($tenant)->create();
    $recipeB = Recipe::factory()->for($tenant)->create();
    $lineA = RecipeIngredientLine::create(['recipe_id' => $recipeA->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);
    $lineB = RecipeIngredientLine::create(['recipe_id' => $recipeB->id, 'ingredient_id' => $from->id, 'quantity' => 0.5, 'unit' => Unit::Kilogramo->value]);

    $result = catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect($result['recipes'])->toBe(2)
        ->and($result['merged'])->toBe(0)
        ->and($lineA->fresh()->ingredient_id)->toBe($to->id)
        ->and((float) $lineA->fresh()->quantity)->toBe(100.0)
        ->and($lineB->fresh()->ingredient_id)->toBe($to->id)
        ->and($lineB->fresh()->unit)->toBe(Unit::Kilogramo)
        ->and((float) $lineB->fresh()->quantity)->toBe(0.5);
});

test('reemplazar un ingrediente recalcula el costo de la receta y de su ancestro', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 1]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 5]);

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 1, 'yield_unit' => Unit::Kilogramo->value]);
    RecipeIngredientLine::create(['recipe_id' => $child->id, 'ingredient_id' => $from->id, 'quantity' => 1000, 'unit' => Unit::Gramo->value]);
    propagateRecipeCosts($child);

    $parent = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    RecipeSubrecipeLine::create(['recipe_id' => $parent->id, 'child_recipe_id' => $child->id, 'quantity_used' => 1, 'unit' => Unit::Kilogramo->value]);
    propagateRecipeCosts($parent);

    $costBefore = (float) $child->fresh()->unit_cost;
    $parentCostBefore = (float) $parent->fresh()->unit_cost;

    catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect((float) $child->fresh()->unit_cost)->not->toBe($costBefore)
        ->and((float) $parent->fresh()->unit_cost)->not->toBe($parentCostBefore);
});

test('reemplazar un ingrediente con unidad incompatible aborta sin tocar ninguna línea', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad]);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan de campo']);
    $line = RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    expect(fn () => catalogReplacer()->replaceIngredient($from, $to, false, false))
        ->toThrow(HttpException::class);

    expect($line->fresh()->ingredient_id)->toBe($from->id);
});

test('la unidad incompatible en el mensaje nombra la receta bloqueante', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad]);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan de campo']);
    RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    try {
        catalogReplacer()->replaceIngredient($from, $to, false, false);
        $this->fail('Se esperaba un HttpException');
    } catch (HttpException $e) {
        expect($e->getMessage())->toContain('Pan de campo');
    }
});

test('reemplazar fusiona la línea con la que ya existía del ítem destino', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $recipe = Recipe::factory()->for($tenant)->create();
    $fromLine = RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $toLine = RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $to->id, 'quantity' => 1, 'unit' => Unit::Kilogramo->value]);

    $result = catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect($result['merged'])->toBe(1)
        ->and(RecipeIngredientLine::find($fromLine->id))->toBeNull()
        ->and((float) $toLine->fresh()->quantity)->toBe(1.5) // 1kg + 500gr convertidos a kg
        ->and($toLine->fresh()->unit)->toBe(Unit::Kilogramo);
});

test('reemplazar migra los vínculos de proveedor al ítem destino', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $link = SupplierProductLink::factory()->create([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $from->id,
    ]);

    catalogReplacer()->replaceIngredient($from, $to, false, true);

    expect($link->fresh()->purchaseable_id)->toBe($to->id)
        ->and($link->fresh()->purchaseable_type)->toBe(CatalogItemType::Ingredient->value);
});

test('sin pedirlo, los vínculos de proveedor no se tocan', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $link = SupplierProductLink::factory()->create([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $from->id,
    ]);

    catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect($link->fresh()->purchaseable_id)->toBe($from->id);
});

test('reemplazar deja el historial de stock y de compras intacto', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-07']);
    $purchaseLine = $purchase->lines()->create([
        'raw_name' => 'HARINA', 'purchaseable_type' => 'ingredient', 'purchaseable_id' => $from->id,
        'quantity_purchased' => 1, 'purchase_unit' => 'kg', 'unit_price' => 1000, 'subtotal' => 1000,
    ]);
    $movement = StockMovement::create([
        'tenant_id' => $tenant->id, 'location_id' => $tenant->defaultLocation()->id,
        'stockable_type' => 'ingredient', 'stockable_id' => $from->id,
        'type' => 'purchase', 'quantity' => 1000, 'unit_cost' => 1,
        'reference_type' => 'purchase_line', 'reference_id' => $purchaseLine->id,
    ]);
    $recipe = Recipe::factory()->for($tenant)->create();
    RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect(PurchaseLine::find($purchaseLine->id)->purchaseable_id)->toBe($from->id)
        ->and(StockMovement::find($movement->id)->stockable_id)->toBe($from->id);
});

test('reemplazar puede desactivar el ítem viejo', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'active' => true]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    catalogReplacer()->replaceIngredient($from, $to, true, false);

    expect($from->fresh()->active)->toBeFalse();
});

test('un ingrediente sin uso en recetas no rompe el reemplazo', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    $result = catalogReplacer()->replaceIngredient($from, $to, false, false);

    expect($result['recipes'])->toBe(0)
        ->and($result['merged'])->toBe(0);
});

test('aislamiento: no se puede reemplazar por un ingrediente de otro tenant', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    $otherTenant = Tenant::factory()->create();
    $foreign = Ingredient::factory()->for($otherTenant)->create(['unit' => Unit::Gramo]);

    expect(fn () => catalogReplacer()->replaceIngredient($from, $foreign, false, false))
        ->toThrow(HttpException::class);
});

// --- Packaging ---

test('reemplazar un descartable conserva cantidad y fusiona duplicados', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Packaging::factory()->for($tenant)->create();
    $to = Packaging::factory()->for($tenant)->create();
    $recipeA = Recipe::factory()->for($tenant)->create();
    $recipeB = Recipe::factory()->for($tenant)->create();
    $lineA = RecipePackagingLine::create(['recipe_id' => $recipeA->id, 'packaging_id' => $from->id, 'quantity' => 2]);
    RecipePackagingLine::create(['recipe_id' => $recipeB->id, 'packaging_id' => $from->id, 'quantity' => 3]);
    $existing = RecipePackagingLine::create(['recipe_id' => $recipeB->id, 'packaging_id' => $to->id, 'quantity' => 1]);

    $result = catalogReplacer()->replacePackaging($from, $to, false);

    expect($result['recipes'])->toBe(2)
        ->and($result['merged'])->toBe(1)
        ->and($lineA->fresh()->packaging_id)->toBe($to->id)
        ->and((float) $lineA->fresh()->quantity)->toBe(2.0)
        ->and((float) $existing->fresh()->quantity)->toBe(4.0); // 1 + 3
});

// --- Sub-recetas ---

test('reemplazar una sub-receta conserva cantidad y unidad de cada línea', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $to = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $parent = Recipe::factory()->for($tenant)->create();
    $line = RecipeSubrecipeLine::create(['recipe_id' => $parent->id, 'child_recipe_id' => $from->id, 'quantity_used' => 2, 'unit' => Unit::Kilogramo->value]);

    $result = catalogReplacer()->replaceSubrecipe($from, $to, false);

    expect($result['recipes'])->toBe(1)
        ->and($line->fresh()->child_recipe_id)->toBe($to->id)
        ->and((float) $line->fresh()->quantity_used)->toBe(2.0);
});

test('reemplazar una sub-receta que crearía un ciclo aborta', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $to = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $x = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);

    // x usa "from" como sub-receta; reemplazarlo haría que x use "to".
    RecipeSubrecipeLine::create(['recipe_id' => $x->id, 'child_recipe_id' => $from->id, 'quantity_used' => 1, 'unit' => Unit::Kilogramo->value]);
    // "to" ya usa a "x" como sub-receta: si x pasara a usar "to", se cerraría
    // el ciclo x → to → x.
    RecipeSubrecipeLine::create(['recipe_id' => $to->id, 'child_recipe_id' => $x->id, 'quantity_used' => 1, 'unit' => Unit::Kilogramo->value]);

    expect(fn () => catalogReplacer()->replaceSubrecipe($from, $to, false))
        ->toThrow(HttpException::class);
});

test('reemplazar una sub-receta por algo que no es semielaborado aborta', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $from = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_unit' => Unit::Kilogramo->value]);
    $to = Recipe::factory()->for($tenant)->create(['is_semi_elaborate' => false]);

    expect(fn () => catalogReplacer()->replaceSubrecipe($from, $to, false))
        ->toThrow(HttpException::class);
});

// --- Camino HTTP ---

test('owner reemplaza un ingrediente vía HTTP y desactiva el viejo', function () {
    [$user, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'active' => true]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $recipe = Recipe::factory()->for($tenant)->create();
    $line = RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    $this->actingAs($user)
        ->post(route('ingredients.replace', $from), [
            'to_id' => $to->id,
            'deactivate_source' => '1',
            'migrate_supplier_links' => '1',
        ])
        ->assertRedirect();

    expect($line->fresh()->ingredient_id)->toBe($to->id)
        ->and($from->fresh()->active)->toBeFalse();
});

test('el endpoint de preview devuelve las recetas afectadas', function () {
    [$user, $tenant] = ownerForCatalogReplacement();
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Bizcochuelo']);
    RecipeIngredientLine::create(['recipe_id' => $recipe->id, 'ingredient_id' => $from->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    $response = $this->actingAs($user)->getJson(route('catalog.replacement-preview', [
        'type' => 'ingredient', 'from_id' => $from->id, 'to_id' => $to->id,
    ]))->assertOk();

    expect($response->json('recipes'))->toBe(['Bizcochuelo'])
        ->and($response->json('incompatible'))->toBe([]);
});

test('viewer no puede reemplazar un ingrediente', function () {
    [, $tenant] = ownerForCatalogReplacement();
    $viewer = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id, 'user_id' => $viewer->id,
        'role' => TenantUserRole::Viewer->value, 'active' => true,
    ]);
    $from = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $to = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);

    $this->actingAs($viewer)
        ->post(route('ingredients.replace', $from), ['to_id' => $to->id])
        ->assertForbidden();
});

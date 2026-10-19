<?php

use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Recipe;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use App\Services\IngredientToProductConverter;
use App\Services\RecipeCostPropagator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

// tenantUserAs() vive en IngredientCrudTest; stockPurchaseFor()/stockLineFor()/lineRecorder()
// viven en StockPurchaseIntegrationTest — se corren siempre juntos en la suite completa.

function converter(): IngredientToProductConverter
{
    return app(IngredientToProductConverter::class);
}

test('convertir un insumo con historial migra compras y stock, y reconstruye el historial de costo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'name' => 'Ketchup Individual',
        'unit' => Unit::Unidad,
        'cost_per_unit' => 50,
    ]);

    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id,
        'purchase_unit' => 'u', 'quantity_purchased' => 100, 'unit_price' => 74, 'subtotal' => 7400,
    ]);
    lineRecorder()->apply($line);

    // Renglón bonificado: entra al stock pero nunca imputa costo (no genera price
    // log ni, por lo tanto, product_cost_log).
    $bonusLine = stockLineFor($purchase, [
        'purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id,
        'purchase_unit' => 'u', 'quantity_purchased' => 10, 'unit_price' => 0, 'subtotal' => 0, 'is_bonus' => true,
    ]);
    lineRecorder()->apply($bonusLine);

    // Carga manual de costo, sin pasar por ninguna compra.
    $ingredient = $ingredient->fresh();
    $ingredient->priceLogs()->create(['cost_per_unit' => 80, 'recorded_at' => now()->subDays(3)]);
    $ingredient->update(['cost_per_unit' => 80]);

    $product = converter()->convert($ingredient->fresh(), null);

    expect($product->type)->toBe(ProductType::Resale)
        ->and((float) $product->cost_per_unit)->toBe(80.0)
        ->and($product->unit)->toBe(Unit::Unidad)
        ->and($product->barcode)->not->toBeNull(); // ProductCodeAssigner

    expect(
        DB::table('purchase_lines')->whereIn('id', [$line->id, $bonusLine->id])
            ->where('purchaseable_type', 'product')->where('purchaseable_id', $product->id)->count()
    )->toBe(2);

    expect(DB::table('stock_movements')->where('stockable_type', 'product')->where('stockable_id', $product->id)->count())->toBe(2)
        ->and(DB::table('stock_movements')->where('stockable_type', 'ingredient')->where('stockable_id', $ingredient->id)->count())->toBe(0)
        ->and(DB::table('stock_levels')->where('stockable_type', 'product')->where('stockable_id', $product->id)->exists())->toBeTrue();

    $logs = ProductCostLog::where('product_id', $product->id)->orderBy('recorded_at')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->source)->toBe(CostLogSource::Manual)
        ->and($logs[0]->purchase_line_id)->toBeNull()
        ->and((float) $logs[0]->cost_per_unit)->toBe(80.0)
        ->and($logs[1]->source)->toBe(CostLogSource::Purchase)
        ->and($logs[1]->purchase_line_id)->toBe($line->id)
        ->and((float) $logs[1]->cost_per_unit)->toBe(74.0);

    $ingredient->refresh();
    expect($ingredient->active)->toBeFalse()
        ->and($ingredient->converted_to_product_id)->toBe($product->id)
        ->and($ingredient->priceLogs()->count())->toBe(2); // no se borra ni se toca
});

test('convertir un insumo usado en recetas migra las líneas al artículo y el costo de la receta no cambia', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Litro, 'cost_per_unit' => 1500]);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Milkshake', 'yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 250, 'unit' => Unit::Mililitro->value]);
    app(RecipeCostPropagator::class)->propagateFrom($recipe);
    $costBefore = (float) $recipe->fresh()->unit_cost;

    $product = converter()->convert($ingredient, null);

    $recipe->refresh();
    $line = $recipe->productLines()->sole();

    expect($costBefore)->toBe(375.0) // 0,25 L × $1500
        ->and($recipe->ingredientLines()->count())->toBe(0)
        ->and($line->product_id)->toBe($product->id)
        ->and((float) $line->quantity)->toBe(250.0)
        ->and($line->unit)->toBe(Unit::Mililitro)
        ->and((float) $recipe->unit_cost)->toBe($costBefore);
});

test('unificar con un artículo existente suma el stock y reapunta compras, vínculos y recetas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Chocolatada 1L', 'unit' => Unit::Litro, 'cost_per_unit' => 1000]);
    $target = Product::factory()->resale()->for($tenant)->create(['name' => 'Chocolatada 1L', 'unit' => Unit::Litro, 'cost_per_unit' => 0]);

    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id,
        'purchase_unit' => 'L', 'quantity_purchased' => 18, 'unit_price' => 1200, 'subtotal' => 21600,
    ]);
    lineRecorder()->apply($line);

    $link = SupplierProductLink::factory()->create([
        'tenant_id' => $tenant->id,
        'supplier_id' => $purchase->supplier_id,
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    // El artículo ya tenía 2 L propios en la misma sucursal.
    seedStock($target, 2, $user);

    $recipe = Recipe::factory()->for($tenant)->create();
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 0.2, 'unit' => Unit::Litro->value]);

    $result = converter()->convert($ingredient->fresh(), null, $target);

    $level = StockLevel::where('stockable_type', 'product')->where('stockable_id', $target->id)->sole();

    expect($result->is($target))->toBeTrue()
        ->and((float) $level->quantity)->toBe(20.0) // 2 propios + 18 del insumo
        ->and(StockLevel::where('stockable_type', 'ingredient')->where('stockable_id', $ingredient->id)->exists())->toBeFalse()
        ->and($line->fresh()->purchaseable_type)->toBe('product')
        ->and($line->fresh()->purchaseable_id)->toBe($target->id)
        ->and($link->fresh()->purchaseable_id)->toBe($target->id)
        ->and((float) $target->fresh()->cost_per_unit)->toBe(1200.0) // sin costo propio: toma el vigente del insumo
        ->and($recipe->productLines()->sole()->product_id)->toBe($target->id)
        ->and($ingredient->fresh()->active)->toBeFalse()
        ->and($ingredient->fresh()->converted_to_product_id)->toBe($target->id)
        ->and(Product::where('tenant_id', $tenant->id)->count())->toBe(1); // no creó otro
});

test('unificar fusiona la línea cuando la receta ya usaba el artículo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Litro]);
    $target = Product::factory()->resale()->for($tenant)->create(['unit' => Unit::Litro, 'cost_per_unit' => 100]);

    $recipe = Recipe::factory()->for($tenant)->create();
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 200, 'unit' => Unit::Mililitro->value]);
    $recipe->productLines()->create(['product_id' => $target->id, 'quantity' => 0.1, 'unit' => Unit::Litro->value]);

    converter()->convert($ingredient, null, $target);

    $merged = $recipe->productLines()->sole();
    expect((float) $merged->quantity)->toBe(0.3) // 0,1 L + 200 ml, en la unidad de la línea existente
        ->and($merged->unit)->toBe(Unit::Litro)
        ->and($recipe->ingredientLines()->count())->toBe(0);
});

test('unificar con un artículo en otra unidad falla sin tocar nada', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Litro]);
    $target = Product::factory()->resale()->for($tenant)->create(['unit' => Unit::Unidad]);

    expect(fn () => converter()->convert($ingredient, null, $target))->toThrow(HttpException::class);

    expect($ingredient->fresh()->active)->toBeTrue()
        ->and($ingredient->fresh()->converted_to_product_id)->toBeNull();
});

test('unificar solo acepta artículos de reventa del mismo negocio', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad]);
    $manufactured = Product::factory()->manufactured()->for($tenant)->create(['unit' => Unit::Unidad]);
    $foreign = Product::factory()->resale()->for(Tenant::factory()->create())->create(['unit' => Unit::Unidad]);

    expect(fn () => converter()->convert($ingredient, null, $manufactured))->toThrow(HttpException::class)
        ->and(fn () => converter()->convert($ingredient, null, $foreign))->toThrow(HttpException::class);
});

test('unificar un insumo en u con subdivisiones deja el divisor en el vínculo de proveedor', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad, 'subdivisions' => 6]);
    $target = Product::factory()->resale()->for($tenant)->create(['unit' => Unit::Unidad]);
    $link = SupplierProductLink::factory()->create([
        'tenant_id' => $tenant->id,
        'supplier_id' => Supplier::factory()->for($tenant)->create()->id,
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'pkg_qty' => null,
    ]);

    converter()->convert($ingredient, null, $target);

    expect((float) $link->fresh()->pkg_qty)->toBe(6.0);
});

test('preview informa qué se movería sin escribir nada', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Litro]);
    $target = Product::factory()->resale()->for($tenant)->create(['unit' => Unit::Litro]);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Milkshake']);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => Unit::Litro->value]);

    $preview = converter()->preview($ingredient, $target);

    expect($preview['recipes'])->toBe(['Milkshake'])
        ->and($preview['units_match'])->toBeTrue()
        ->and($ingredient->fresh()->active)->toBeTrue()
        ->and($recipe->ingredientLines()->count())->toBe(1);
});

test('la ruta de conversión no unifica: ignora product_id y crea un artículo nuevo', function () {
    // Unificar con un artículo existente es solo por consola (catalog:unify-ingredient).
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Chocolatada 1L', 'unit' => Unit::Litro]);
    $existing = Product::factory()->resale()->for($tenant)->create(['name' => 'Otro artículo', 'unit' => Unit::Litro]);

    $this->actingAs($user)
        ->post(route('ingredients.convert-to-product', $ingredient), ['product_id' => $existing->id])
        ->assertRedirect(route('products.index'));

    $converted = $ingredient->fresh()->converted_to_product_id;

    expect($converted)->not->toBeNull()
        ->and($converted)->not->toBe($existing->id)
        ->and(Product::findOrFail($converted)->name)->toBe('Chocolatada 1L');
});

test('convertir un insumo ya convertido falla', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create();
    converter()->convert($ingredient, null);

    expect(fn () => converter()->convert($ingredient->fresh(), null))->toThrow(HttpException::class);
});

test('convertir un insumo inactivo falla', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => false]);

    expect(fn () => converter()->convert($ingredient, null))->toThrow(HttpException::class);
});

test('owner puede convertir un insumo vía HTTP y elegir la categoría del producto', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Agua con gas']);
    $category = $tenant->productCategories()->create(['name' => 'Bebidas', 'producible' => true]);

    $this->actingAs($user)
        ->post(route('ingredients.convert-to-product', $ingredient), ['product_category_id' => $category->id])
        ->assertRedirect(route('products.index'));

    $product = Product::where('tenant_id', $tenant->id)->where('name', 'Agua con gas')->firstOrFail();
    expect($product->product_category_id)->toBe($category->id)
        ->and($ingredient->fresh()->converted_to_product_id)->toBe($product->id);

    // Consume el flash "Insumo convertido: Agua con gas." de la redirección
    // para que no contamine los asserts de abajo (session('status') se
    // muestra en cualquier página, no sólo en la de destino).
    $this->actingAs($user)->get(route('products.index'));

    // El insumo convertido no debe verse más en el listado de Ingredientes,
    // ni siquiera filtrando por inactivos.
    $this->actingAs($user)->get(route('ingredients.index'))
        ->assertOk()
        ->assertDontSee('Agua con gas');

    $this->actingAs($user)->get(route('ingredients.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertDontSee('Agua con gas');
});

test('un insumo inactivo pero no convertido sigue viéndose en el listado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'active' => false]);

    $this->actingAs($user)->get(route('ingredients.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Harina 000');
});

test('aislamiento: no se puede convertir un insumo de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $other = Ingredient::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('ingredients.convert-to-product', $other))
        ->assertNotFound();
});

test('viewer no puede convertir un insumo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('ingredients.convert-to-product', $ingredient))
        ->assertForbidden();
});

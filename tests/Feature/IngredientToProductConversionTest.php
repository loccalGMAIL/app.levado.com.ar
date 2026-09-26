<?php

use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Services\IngredientToProductConverter;
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

test('convertir un insumo usado en una receta (activa o no) falla y nombra la receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create();
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan Casero', 'active' => false]);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);

    try {
        converter()->convert($ingredient, null);
        $this->fail('Se esperaba un HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422)
            ->and($e->getMessage())->toContain('Pan Casero');
    }
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

    // El listado de Insumos tiene que seguir renderizando bien la rama "Ver
    // producto" para el insumo ya convertido.
    $this->actingAs($user)->get(route('ingredients.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee('Ver producto');
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

<?php

use App\Enums\PricingPolicy;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Services\ArticlePriceRecalculator;
use App\Services\ComboExploder;
use App\Services\ProductPriceWriter;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Combo «Desayuno»: 2 medialunas (elaborado, $15 c/u) + 1 café (reventa, $40).
 *
 * @return array{0: Product, 1: Product, 2: Product}
 */
function comboDesayuno($tenant): array
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'unit_cost' => 15,
        'yield_quantity' => 12,
        'yield_unit' => Unit::Unidad->value,
        'labor_hours' => 0,
    ]);
    $medialuna = manufacturedProduct($tenant, $recipe);
    $cafe = Product::factory()->for($tenant)->resale()->create(['name' => 'Café', 'cost_per_unit' => 40]);

    $combo = Product::factory()->for($tenant)->combo()->create(['name' => 'Desayuno']);
    $combo->comboLines()->create(['product_id' => $medialuna->id, 'quantity' => 2]);
    $combo->comboLines()->create(['product_id' => $cafe->id, 'quantity' => 1]);

    return [$combo, $medialuna, $cafe];
}

// --- Costo ---

test('el costo de un combo es la suma del costo de sus componentes por su cantidad', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo] = comboDesayuno($tenant);

    expect($combo->currentCost())->toBe(70.0)
        ->and($combo->fullCost(0.0))->toBe(70.0)
        ->and($combo->currentCostSource())->toBe('combo');
});

test('el costo del combo es null si algún componente no tiene costo o no hay componentes', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, , $cafe] = comboDesayuno($tenant);
    $cafe->update(['cost_per_unit' => null]);

    $vacio = Product::factory()->for($tenant)->combo()->create();

    expect($combo->fresh()->currentCost())->toBeNull()
        ->and($vacio->currentCost())->toBeNull();
});

test('cambiar el costo de un componente recalcula el precio con política del combo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, , $cafe] = comboDesayuno($tenant);
    $list = $tenant->defaultPriceList();

    app(ProductPriceWriter::class)->setPolicy($combo, $list, PricingPolicy::Markup, 100);
    expect($combo->currentPrice($list))->toBe(140.0);

    $cafe->update(['cost_per_unit' => 50]);
    app(ArticlePriceRecalculator::class)->recompute($cafe);

    expect($combo->currentPrice($list))->toBe(160.0);
});

// --- Stock ---

test('vender un combo descuenta el stock de cada componente y no el del combo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, $medialuna, $cafe] = comboDesayuno($tenant);
    $location = $tenant->defaultLocation();
    seedStock($medialuna, 10, $user);
    seedStock($cafe, 5, $user);

    $movements = app(ComboExploder::class)->registerSale($combo, 3, $location, $user);

    expect($movements)->toHaveCount(2)
        ->and((float) stockService()->levelFor($medialuna, $location)->quantity)->toBe(4.0)
        ->and((float) stockService()->levelFor($cafe, $location)->quantity)->toBe(2.0)
        ->and(stockService()->levelFor($combo, $location))->toBeNull();

    $sale = StockMovement::where('type', StockMovementType::Sale->value)->where('stockable_id', $cafe->id)->first();

    expect((float) $sale->quantity)->toBe(-3.0)
        ->and((float) $sale->unit_cost)->toBe(40.0)
        ->and($sale->reason)->toBe('Combo: Desayuno')
        ->and($sale->reference_type)->toBe('combo')
        ->and($sale->reference_id)->toBe($combo->id);
});

test('la venta de un combo se puede revertir por su referencia', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, $medialuna, $cafe] = comboDesayuno($tenant);
    $location = $tenant->defaultLocation();

    app(ComboExploder::class)->registerSale($combo, 2, $location, $user, 'sale_line', 99);
    stockService()->reverseMovementsFor('sale_line', 99, $user);

    expect((float) stockService()->levelFor($medialuna, $location)->quantity)->toBe(0.0)
        ->and((float) stockService()->levelFor($cafe, $location)->quantity)->toBe(0.0);
});

test('vender un combo sin stock suficiente deja el stock en negativo, sin bloquear', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, $medialuna] = comboDesayuno($tenant);

    app(ComboExploder::class)->registerSale($combo, 1, $tenant->defaultLocation(), $user);

    expect((float) stockService()->levelFor($medialuna, $tenant->defaultLocation())->quantity)->toBe(-2.0);
});

test('un combo no admite movimientos de stock propios', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo] = comboDesayuno($tenant);

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['type' => 'product', 'id' => $combo->id]), [
            'quantity' => 5, 'direction' => 'in', 'reason' => 'x',
        ])
        ->assertNotFound();

    expect(fn () => stockService()->registerAdjustment($combo, $tenant->defaultLocation(), 5, 'x', $user))
        ->toThrow(HttpException::class);
});

test('la disponibilidad del combo la da el componente más escaso', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, $medialuna, $cafe] = comboDesayuno($tenant);
    seedStock($medialuna, 7, $user);
    seedStock($cafe, 10, $user);

    $availability = app(ComboExploder::class)->availability(collect([$combo->load('comboLines')]), $tenant->defaultLocation());

    expect($availability[$combo->id])->toBe(3);
});

// --- Filtros: el combo no aparece donde hay stock propio ---

test('los combos no aparecen en existencias ni en el catálogo, y sí en su pestaña y en la matriz', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo] = comboDesayuno($tenant);
    $combo->update(['name' => 'Promo Mañanera']);

    $this->actingAs($user)->get(route('stock.index', ['type' => 'product']))->assertOk()->assertDontSee('Promo Mañanera');
    $this->actingAs($user)->get(route('products.index'))->assertOk()->assertDontSee('Promo Mañanera');
    $this->actingAs($user)->get(route('products.matrix'))->assertOk()->assertSee('Promo Mañanera');
    $this->actingAs($user)->get(route('products.combos.index'))->assertOk()
        ->assertSee('Promo Mañanera')
        ->assertSee('1 u Café');
});

test('un combo no es un artículo producible', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo] = comboDesayuno($tenant);

    expect(Product::query()->producible()->whereKey($combo->id)->exists())->toBeFalse();
});

// --- ABM ---

test('owner crea un combo con componentes, unidad y código propio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $cafe = Product::factory()->for($tenant)->resale()->create();
    $tostado = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->post(route('products.combos.store'), [
            'name' => 'Merienda',
            'lines' => [
                ['product_id' => $cafe->id, 'quantity' => 1],
                ['product_id' => $tostado->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('products.combos.index'))
        ->assertSessionHasNoErrors();

    $combo = $tenant->products()->where('name', 'Merienda')->firstOrFail();

    expect($combo->type)->toBe(ProductType::Combo)
        ->and($combo->unit)->toBe(Unit::Unidad)
        ->and($combo->barcode)->toHaveLength(13)
        ->and($combo->comboLines)->toHaveCount(2)
        ->and((float) $combo->comboLines->firstWhere('product_id', $tostado->id)->quantity)->toBe(2.0);
});

test('editar un combo reemplaza sus componentes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, $medialuna] = comboDesayuno($tenant);

    $this->actingAs($user)
        ->put(route('products.combos.update', $combo), [
            'name' => 'Desayuno doble',
            'barcode' => $combo->barcode,
            'lines' => [['product_id' => $medialuna->id, 'quantity' => 4]],
        ])
        ->assertSessionHasNoErrors();

    $combo->refresh();

    expect($combo->name)->toBe('Desayuno doble')
        ->and($combo->comboLines)->toHaveCount(1)
        ->and($combo->currentCost())->toBe(60.0);
});

test('un combo necesita al menos dos unidades y no puede contener otro combo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, , $cafe] = comboDesayuno($tenant);

    $this->actingAs($user)
        ->post(route('products.combos.store'), ['name' => 'Solo', 'lines' => [['product_id' => $cafe->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('lines');

    $this->actingAs($user)
        ->post(route('products.combos.store'), ['name' => 'Anidado', 'lines' => [
            ['product_id' => $cafe->id, 'quantity' => 1],
            ['product_id' => $combo->id, 'quantity' => 1],
        ]])
        ->assertSessionHasErrors('lines.1.product_id');

    $this->actingAs($user)
        ->post(route('products.combos.store'), ['name' => 'Sin nada'])
        ->assertSessionHasErrors('lines');
});

test('un combo no acepta componentes de otro negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $ajeno = Product::factory()->resale()->create();

    $this->actingAs($user)
        ->post(route('products.combos.store'), ['name' => 'Cruzado', 'lines' => [['product_id' => $ajeno->id, 'quantity' => 2]]])
        ->assertSessionHasErrors('lines.0.product_id');
});

test('el alta común de artículos no permite crear ni editar combos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo] = comboDesayuno($tenant);

    $this->actingAs($user)
        ->post(route('products.store'), ['name' => 'X', 'type' => 'combo', 'unit' => Unit::Unidad->value])
        ->assertSessionHasErrors('type');

    $this->actingAs($user)
        ->put(route('products.update', $combo), ['name' => 'X', 'type' => 'resale', 'unit' => Unit::Unidad->value, 'cost_per_unit' => 1])
        ->assertNotFound();
});

test('no se puede desactivar un artículo que forma parte de un combo activo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    [$combo, , $cafe] = comboDesayuno($tenant);

    $this->actingAs($user)
        ->patch(route('products.toggle-active', $cafe))
        ->assertSessionHasErrors('toggle');
    expect($cafe->fresh()->active)->toBeTrue();

    $this->actingAs($user)->patch(route('products.toggle-active', $combo))->assertSessionHasNoErrors();
    $this->actingAs($user)->patch(route('products.toggle-active', $cafe))->assertSessionHasNoErrors();
    expect($cafe->fresh()->active)->toBeFalse();

    // Con un componente dado de baja el combo no se puede reactivar.
    $this->actingAs($user)
        ->patch(route('products.toggle-active', $combo))
        ->assertSessionHasErrors('toggle');
});

test('un rol sin permisos no puede crear combos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $cafe = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->post(route('products.combos.store'), ['name' => 'Merienda', 'lines' => [['product_id' => $cafe->id, 'quantity' => 2]]])
        ->assertForbidden();
});

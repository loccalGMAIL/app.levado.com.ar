<?php

use App\Enums\ContainerType;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\PurchaseLineRecorder;
use App\Services\RecipeCostPropagator;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Refresca los caches unit_cost/labor_hours de una receta sembrada a mano.
 * En producción los mantiene RecipeCostPropagator vía los controllers; los
 * tests que crean líneas directo con ::create() deben llamarlo antes de
 * asertar sobre vistas que leen los caches (dashboard, matriz, precios).
 */
function propagateRecipeCosts(Recipe $recipe): void
{
    app(RecipeCostPropagator::class)->propagateFrom($recipe);
}

function ownerForFixedCost(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'General']);

    return [$user, $tenant, $category];
}

function tenantUserAs(TenantUserRole $role): array
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

/** Compra de $qty unidades del producto a $unitPrice/u, ya imputada. */
function buyResale(Product $product, $tenant, float $qty, float $unitPrice): void
{
    $line = stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'quantity_purchased' => $qty,
        'purchase_unit' => 'u',
        'unit_price' => $unitPrice,
    ]);
    lineRecorder()->apply($line);
}

/** Arma un elaborado con receta (1 ingrediente) en una categoría producible, para un tenant nuevo. */
function productionSetup(TenantUserRole $role = TenantUserRole::Owner): array
{
    [$user, $tenant] = tenantUserAs($role);
    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $category = $tenant->productCategories()->create(['name' => 'Producción', 'producible' => true]);
    $product->update(['product_category_id' => $category->id]);

    return [$user, $tenant, $product, $harina];
}

/** Producto elaborado (unidad u) ligado a una receta, en el tenant dado. */
function manufacturedProduct($tenant, Recipe $recipe): Product
{
    return Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
        'recipe_id' => $recipe->id,
    ]);
}

/** Deja stock inicial de un ítem en la sucursal default del tenant. */
function seedStock(Ingredient|Packaging|Product $item, float $quantity, $user): void
{
    app(StockService::class)->registerAdjustment($item, $item->tenant->defaultLocation(), $quantity, 'Carga inicial test', $user);
}

function ownerForScan(): array
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

function stockPurchaseOwner(): array
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

function stockPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-07']);
}

function stockLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'HARINA 000',
        'quantity_purchased' => 2,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
        'subtotal' => 2000,
    ], $overrides));
}

function lineRecorder(): PurchaseLineRecorder
{
    return app(PurchaseLineRecorder::class);
}

function stockTenantUser(TenantUserRole $role = TenantUserRole::Owner): array
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

function stockService(): StockService
{
    return app(StockService::class);
}

function userForVariableExpense(TenantUserRole $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);
    $category = $tenant->variableExpenseCategories()->create(['name' => 'General']);

    return [$user, $tenant, $category];
}

function ownerForVariableExpense(): array
{
    return userForVariableExpense(TenantUserRole::Owner);
}

/*
|--------------------------------------------------------------------------
| Producción en dos etapas (amasado y horneado)
|--------------------------------------------------------------------------
*/

/**
 * Artículo base elaborado (unidad u) con su receta de $yield unidades. Por
 * defecto pasa por cámara; $tinPer > 0 hace que se pida en latas de ese rinde.
 */
function doughProduct(Tenant $tenant, string $name = 'Medialunas', bool $proofing = true, float $yield = 12, ?float $tinPer = null): Product
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'name' => $name,
        'yield_quantity' => $yield,
        'yield_unit' => Unit::Unidad->value,
        'uses_proofing' => $proofing,
        'container_type' => $tinPer !== null ? ContainerType::Tin->value : null,
        'units_per_container' => $tinPer,
    ]);

    return Product::factory()->for($tenant)->create([
        'name' => $name,
        'type' => ProductType::Manufactured->value,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
        'recipe_id' => $recipe->id,
    ]);
}

/**
 * Carga a mano en una planilla activa de pedidos lo de una semana (lunes a
 * domingo) para un artículo. Crea la planilla si no se pasa.
 */
function sheetOrder(Tenant $tenant, Product $product, array $quantities, string $monday, ?OrderSheet $sheet = null, ?ContainerType $container = null): OrderSheet
{
    $sheet ??= OrderSheet::factory()->for($tenant)->create();
    $week = OrderSheetWeek::query()->where('order_sheet_id', $sheet->id)->whereDate('starts_on', $monday)->first()
        ?? OrderSheetWeek::factory()->for($sheet)->startingOn($monday)->create();

    OrderSheetLine::factory()->for($week, 'week')->withQuantities($quantities)->create([
        'product_id' => $product->id,
        'container_type' => $container,
    ]);

    return $sheet;
}

/** Deja stock de un artículo en la cámara de leudado del negocio. */
function seedChamber(Product $product, float $quantity, User $user): void
{
    app(StockService::class)->registerAdjustment($product, $product->tenant->proofingChamber(), $quantity, 'Carga inicial test', $user);
}

/**
 * Amasado de prueba: artículo de 12 por receta que lleva 1.200 g de harina
 * (a $0,01 el gramo) y una bolsa por unidad (a $5). Devuelve el usuario, el
 * negocio, el artículo, la harina y la bolsa.
 *
 * @return array{0: User, 1: Tenant, 2: Product, 3: Ingredient, 4: Packaging}
 */
function bakerySetup(bool $proofing = true, TenantUserRole $role = TenantUserRole::Owner): array
{
    [$user, $tenant] = tenantUserAs($role);
    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $bolsa = Packaging::factory()->for($tenant)->create(['name' => 'Bolsa', 'cost_per_unit' => 5]);
    $product = doughProduct($tenant, 'Medialunas', proofing: $proofing, yield: 12);
    $product->recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 1200, 'unit' => Unit::Gramo->value]);
    $product->recipe->packagingLines()->create(['packaging_id' => $bolsa->id, 'quantity' => 12]);
    propagateRecipeCosts($product->recipe);

    return [$user, $tenant, $product->fresh(['recipe']), $harina, $bolsa];
}

/** Stock de un ítem en una ubicación (0 si no tiene fila). */
function stockAt(Ingredient|Packaging|Product $item, Location $location): float
{
    return (float) (app(StockService::class)->levelFor($item, $location)->quantity ?? 0);
}

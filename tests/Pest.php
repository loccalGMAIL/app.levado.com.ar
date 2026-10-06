<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ProductionOrderService;
use App\Services\ProductionService;
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

function ordersService(): ProductionOrderService
{
    return app(ProductionOrderService::class);
}

function productionOrderService(): ProductionOrderService
{
    return app(ProductionOrderService::class);
}

/** Orden con un pedido y una línea artículo+cantidad, lista para producir. */
function orderWithLine(Product $product, float $quantity, $tenant, ?Location $destination = null): ProductionOrder
{
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create([
        'location_id' => $tenant->defaultLocation()->id,
    ]);

    $request = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'location',
        'destination_id' => ($destination ?? Location::factory()->for($tenant)->create())->id,
    ]);

    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit' => $product->unit->value,
    ]);

    return $order->fresh();
}

function productionService(): ProductionService
{
    return app(ProductionService::class);
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

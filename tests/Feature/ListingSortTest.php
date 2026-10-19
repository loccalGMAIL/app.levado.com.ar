<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\AdminAuditLog;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\FixedCost;
use App\Models\Ingredient;
use App\Models\Invitation;
use App\Models\LaborType;
use App\Models\Packaging;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VariableExpense;
use App\Services\StockService;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * Orden por columna de los listados (?sort=&dir=). Las aserciones miran sólo el
 * <tbody>: los selects de filtros y de los modales repiten nombres en orden
 * alfabético y harían pasar un assertSeeInOrder sobre la página entera.
 */

function listingRows(TestResponse $response, bool $lastTable = false): string
{
    $html = $response->assertOk()->getContent();
    $fromBody = $lastTable ? Str::afterLast($html, '<tbody') : Str::after($html, '<tbody');

    return Str::before($fromBody, '</tbody>');
}

/**
 * @param  list<string>  $texts
 */
function expectRowsInOrder(string $rows, array $texts): void
{
    $offset = 0;

    foreach ($texts as $text) {
        $position = strpos($rows, $text, $offset);
        expect($position)->not->toBeFalse("«{$text}» no aparece en el orden esperado");
        $offset = $position + strlen($text);
    }
}

function listingSortSuperAdmin(): User
{
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => Tenant::factory()->create()->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::SuperAdmin->value,
        'active' => true,
    ]);

    return $user;
}

// --- Insumos y envases ---

test('insumos se ordenan por proveedor', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $alfa = Supplier::factory()->for($tenant)->create(['name' => 'Alfa SRL']);
    $zeta = Supplier::factory()->for($tenant)->create(['name' => 'Zeta SA']);
    Ingredient::factory()->for($tenant)->create(['name' => 'Arroz', 'supplier_id' => $zeta->id]);
    Ingredient::factory()->for($tenant)->create(['name' => 'Zanahoria', 'supplier_id' => $alfa->id]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'supplier']))), ['Zanahoria', 'Arroz']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'supplier', 'dir' => 'desc']))), ['Arroz', 'Zanahoria']);
});

test('insumos se ordenan por stock y el que no tiene movimientos cuenta como 0', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => Unit::Gramo]);
    Ingredient::factory()->for($tenant)->create(['name' => 'Sal fina', 'unit' => Unit::Gramo]);
    app(StockService::class)->registerAdjustment($harina, $tenant->defaultLocation(), 100, 'Carga inicial', $user);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'stock']))), ['Sal fina', 'Harina 000']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'stock', 'dir' => 'desc']))), ['Harina 000', 'Sal fina']);
});

test('los empates se desempatan por id en el sentido pedido', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->create(['name' => 'Primero', 'brand' => 'Misma']);
    Ingredient::factory()->for($tenant)->create(['name' => 'Segundo', 'brand' => 'Misma']);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'brand']))), ['Primero', 'Segundo']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'brand', 'dir' => 'desc']))), ['Segundo', 'Primero']);
});

test('un sort que no está en la lista blanca usa el orden por defecto', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->create(['name' => 'Zapallo']);
    Ingredient::factory()->for($tenant)->create(['name' => 'Arroz']);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('ingredients.index', ['sort' => 'tenant_id; drop table x']))), ['Arroz', 'Zapallo']);
});

test('envases se ordenan por estado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Packaging::factory()->for($tenant)->create(['name' => 'Bolsa kraft', 'active' => true]);
    Packaging::factory()->for($tenant)->create(['name' => 'Caja torta', 'active' => false]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('packaging.index', ['sort' => 'active']))), ['Caja torta', 'Bolsa kraft']);
});

// --- Costos ---

test('gastos variables se ordenan por categoría', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $almacen = $tenant->variableExpenseCategories()->create(['name' => 'Almacén']);
    $servicios = $tenant->variableExpenseCategories()->create(['name' => 'Servicios']);
    VariableExpense::factory()->for($tenant)->create(['name' => 'Gas natural', 'variable_expense_category_id' => $servicios->id]);
    VariableExpense::factory()->for($tenant)->create(['name' => 'Luz eléctrica', 'variable_expense_category_id' => $almacen->id]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('variable-expenses.index', ['sort' => 'category']))), ['Luz eléctrica', 'Gas natural']);
});

test('costos fijos se ordenan por categoría', function () {
    [$user, $tenant, $general] = ownerForFixedCost();
    $alquileres = $tenant->fixedCostCategories()->create(['name' => 'Alquileres']);
    FixedCost::factory()->for($tenant)->create(['name' => 'Alfa costo', 'fixed_cost_category_id' => $general->id]);
    FixedCost::factory()->for($tenant)->create(['name' => 'Zeta costo', 'fixed_cost_category_id' => $alquileres->id]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('fixed-costs.index', ['sort' => 'category']))), ['Zeta costo', 'Alfa costo']);
});

test('tipos de mano de obra se ordenan por estado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    LaborType::factory()->for($tenant)->create(['name' => 'Ayudante', 'active' => true]);
    LaborType::factory()->for($tenant)->create(['name' => 'Panadero', 'active' => false]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('labor-types.index', ['sort' => 'active']))), ['Panadero', 'Ayudante']);
});

test('listas de precios se ordenan por precios cargados', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $conPrecios = PriceList::factory()->for($tenant)->create(['name' => 'Cafeterías']);
    PriceList::factory()->for($tenant)->create(['name' => 'Eventos']);
    // La columna cuenta PriceList::prices(), que hoy apunta a recipe_prices.
    RecipePrice::factory()->create(['tenant_id' => $tenant->id, 'price_list_id' => $conPrecios->id, 'recipe_id' => Recipe::factory()->for($tenant)->create()->id]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('price-lists.index', ['sort' => 'prices_count', 'dir' => 'desc']))), ['Cafeterías', 'Eventos']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('price-lists.index', ['sort' => 'prices_count']))), ['Eventos', 'Cafeterías']);
});

test('recetas se ordenan por estado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Recipe::factory()->for($tenant)->create(['name' => 'Budín', 'active' => true]);
    Recipe::factory()->for($tenant)->create(['name' => 'Medialunas', 'active' => false]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('recipes.index', ['sort' => 'active']))), ['Medialunas', 'Budín']);
});

// --- Artículos ---

test('artículos se ordenan por precio de la lista elegida, sin precio primero', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $priceList = $tenant->defaultPriceList();
    $caro = Product::factory()->for($tenant)->create(['name' => 'Alfajor']);
    $barato = Product::factory()->for($tenant)->create(['name' => 'Bizcocho']);
    Product::factory()->for($tenant)->create(['name' => 'Chipá']);
    ProductPrice::create(['tenant_id' => $tenant->id, 'price_list_id' => $priceList->id, 'product_id' => $caro->id, 'price' => 500]);
    ProductPrice::create(['tenant_id' => $tenant->id, 'price_list_id' => $priceList->id, 'product_id' => $barato->id, 'price' => 100]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('products.index', ['sort' => 'price']))), ['Chipá', 'Bizcocho', 'Alfajor']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('products.index', ['sort' => 'price', 'dir' => 'desc']))), ['Alfajor', 'Bizcocho', 'Chipá']);
});

test('artículos se ordenan por código: el de barras y, si no hay, el SKU', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Product::factory()->for($tenant)->create(['name' => 'Alfajor', 'sku' => 'Z-001']);
    Product::factory()->for($tenant)->create(['name' => 'Bizcocho', 'barcode' => '7790001']);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('products.index', ['sort' => 'code']))), ['Bizcocho', 'Alfajor']);
});

test('el historial de producción se ordena por artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $zapallo = Product::factory()->for($tenant)->create(['name' => 'Pan de zapallo']);
    $alfajor = Product::factory()->for($tenant)->create(['name' => 'Alfajor de maicena']);
    Production::factory()->for($tenant)->create(['product_id' => $alfajor->id, 'produced_at' => now()->subDay()]);
    Production::factory()->for($tenant)->create(['product_id' => $zapallo->id, 'produced_at' => now()]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('products.history'))), ['Pan de zapallo', 'Alfajor de maicena']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('products.history', ['sort' => 'product']))), ['Alfajor de maicena', 'Pan de zapallo']);
});

// --- Compras, notas de crédito y stock ---

test('notas de crédito se ordenan por cantidad de renglones', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tres = CreditNote::factory()->create(['tenant_id' => $tenant->id, 'note_number' => 'NC-A']);
    $una = CreditNote::factory()->create(['tenant_id' => $tenant->id, 'note_number' => 'NC-B']);
    CreditNoteLine::factory()->count(3)->create(['credit_note_id' => $tres->id]);
    CreditNoteLine::factory()->create(['credit_note_id' => $una->id]);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('credit-notes.index', ['sort' => 'lines', 'dir' => 'asc']))), ['NC-B', 'NC-A']);
});

test('stock se ordena por alerta: sin alerta antes que bajo mínimo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $azucar = Ingredient::factory()->for($tenant)->create(['name' => 'Azúcar', 'unit' => Unit::Gramo]);
    $sal = Ingredient::factory()->for($tenant)->create(['name' => 'Sal', 'unit' => Unit::Gramo]);
    $location = $tenant->defaultLocation();
    app(StockService::class)->registerAdjustment($azucar, $location, 10, 'Carga inicial', $user);
    app(StockService::class)->setMinQuantity($azucar, $location, 50);
    app(StockService::class)->registerAdjustment($sal, $location, 100, 'Carga inicial', $user);

    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('stock.index', ['sort' => 'alert']))), ['Sal', 'Azúcar']);
    expectRowsInOrder(listingRows($this->actingAs($user)->get(route('stock.index', ['sort' => 'alert', 'dir' => 'desc']))), ['Azúcar', 'Sal']);
});

test('compras usa x-sortable-th en todas sus columnas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Purchase::factory()->create(['tenant_id' => $tenant->id]);

    $html = $this->actingAs($user)->get(route('purchases.index'))->assertOk()->getContent();

    foreach (['date', 'invoice_number', 'supplier', 'items', 'total'] as $column) {
        expect($html)->toContain("sort={$column}&amp;dir=");
    }
});

// --- Personal ---

test('personal ordena miembros e invitaciones por separado', function () {
    [$owner, $tenant] = tenantUserAs(TenantUserRole::Owner);
    foreach (['Zoe Miembro', 'Ana Miembro'] as $name) {
        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create(['name' => $name])->id,
            'role' => TenantUserRole::Admin->value,
            'active' => true,
        ]);
    }
    Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'aaa@ejemplo.com']);
    Invitation::factory()->create(['tenant_id' => $tenant->id, 'email' => 'zzz@ejemplo.com']);

    $response = $this->actingAs($owner)->get(route('team.index', ['sort' => 'name', 'inv_sort' => 'email', 'inv_dir' => 'desc']));

    expectRowsInOrder(listingRows($response), ['zzz@ejemplo.com', 'aaa@ejemplo.com']);
    expectRowsInOrder(listingRows($response, lastTable: true), ['Ana Miembro', 'Zoe Miembro']);
});

// --- Backoffice ---

test('el backoffice ordena tenants por nombre', function () {
    $admin = listingSortSuperAdmin();
    Tenant::factory()->create(['name' => 'Zeta Panadería']);
    Tenant::factory()->create(['name' => 'Alfa Panadería']);

    expectRowsInOrder(listingRows($this->actingAs($admin)->get(route('admin.tenants.index', ['sort' => 'name']))), ['Alfa Panadería', 'Zeta Panadería']);
    expectRowsInOrder(listingRows($this->actingAs($admin)->get(route('admin.tenants.index', ['sort' => 'name', 'dir' => 'desc']))), ['Zeta Panadería', 'Alfa Panadería']);
});

test('el backoffice ordena usuarios por email', function () {
    $admin = listingSortSuperAdmin();
    User::factory()->create(['email' => 'zz-orden@ejemplo.com']);
    User::factory()->create(['email' => 'aa-orden@ejemplo.com']);

    expectRowsInOrder(listingRows($this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'email']))), ['aa-orden@ejemplo.com', 'zz-orden@ejemplo.com']);
});

test('el backoffice ordena la auditoría por acción', function () {
    $admin = listingSortSuperAdmin();
    foreach (['zeta.updated', 'alfa.created'] as $action) {
        AdminAuditLog::create(['actor_user_id' => $admin->id, 'target_type' => 'tenant', 'target_id' => 1, 'action' => $action, 'payload' => []]);
    }

    expectRowsInOrder(listingRows($this->actingAs($admin)->get(route('admin.audit-logs.index', ['sort' => 'action']))), ['alfa.created', 'zeta.updated']);
});

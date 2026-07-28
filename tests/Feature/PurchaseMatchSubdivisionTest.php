<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/**
 * La pantalla de vincular manda el costo ya expresado por sub-unidad (lo divide
 * match.js) y matchLine() lo imputa vía applyWithCost(), un camino distinto al de
 * apply(). Estos tests anclan esa ruta HTTP, que es donde el descartable con
 * subdivisión se guardaba con el precio del bulto entero.
 */
function ownerForMatch(): array
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

function matchPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-28']);
}

function pendingLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'SOBRE KRAFT N°4',
        'quantity_purchased' => 20,
        'purchase_unit' => 'u',
        'unit_price' => 2000,
        'subtotal' => 40000,
    ], $overrides));
}

test('vincular un descartable con subdivisión guarda el costo por sub-unidad y deriva el del bulto', function () {
    [$user, $tenant] = ownerForMatch();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'subdivision_label' => 'sobre',
        'cost_per_unit' => 1,
    ]);
    $purchase = matchPurchaseFor($tenant);
    $line = pendingLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "packaging:{$packaging->id}",
            'unit_cost' => '20',   // $2000 el bulto ÷ 100 sobres
        ])
        ->assertRedirect();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(20.0)
        ->and((float) $packaging->cost_per_package)->toBe(2000.0);

    // 20 bultos × 100 sobres: el stock se lleva en sub-unidades.
    expect((float) $packaging->stockLevels()->first()->quantity)->toBe(2000.0);
});

test('sobrescribir el costo al vincular recalcula el precio del bulto en vez de dejarlo viejo', function () {
    [$user, $tenant] = ownerForMatch();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'cost_per_unit' => 20,
        'cost_per_package' => 2000,
    ]);
    $purchase = matchPurchaseFor($tenant);
    $line = pendingLineFor($purchase, ['unit_price' => 2500, 'subtotal' => 50000]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "packaging:{$packaging->id}",
            'unit_cost' => '25',
        ])
        ->assertRedirect();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(25.0)
        ->and((float) $packaging->cost_per_package)->toBe(2500.0);
});

test('vincular un descartable sin subdivisión no toca cost_per_package', function () {
    [$user, $tenant] = ownerForMatch();
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => null,
        'cost_per_unit' => 1,
    ]);
    $purchase = matchPurchaseFor($tenant);
    $line = pendingLineFor($purchase, ['quantity_purchased' => 5, 'unit_price' => 300, 'subtotal' => 1500]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "packaging:{$packaging->id}",
            'unit_cost' => '300',
        ])
        ->assertRedirect();

    $packaging->refresh();

    expect((float) $packaging->cost_per_unit)->toBe(300.0)
        ->and($packaging->cost_per_package)->toBeNull()
        ->and((float) $packaging->stockLevels()->first()->quantity)->toBe(5.0);
});

test('vincular un insumo con subdivisión refresca el precio del bulto en vez de dejar el anterior', function () {
    [$user, $tenant] = ownerForMatch();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'subdivisions' => 24,
        'subdivision_label' => 'galleta',
        'cost_per_unit' => 10,
        'cost_per_package' => 240,
    ]);
    $purchase = matchPurchaseFor($tenant);
    $line = pendingLineFor($purchase, [
        'raw_name' => 'GALLETITAS X24',
        'quantity_purchased' => 3,
        'unit_price' => 480,
        'subtotal' => 1440,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
            'unit_cost' => '20',
        ])
        ->assertRedirect();

    $ingredient->refresh();

    expect((float) $ingredient->cost_per_unit)->toBe(20.0)
        ->and((float) $ingredient->cost_per_package)->toBe(480.0);
});

test('vincular un insumo que no se mide por unidad no le pone precio de bulto', function () {
    // subdivisions sólo tiene sentido cuando el insumo se lleva por unidad; el guard
    // replica el de apply().
    [$user, $tenant] = ownerForMatch();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo,
        'subdivisions' => 25,
        'cost_per_unit' => 100,
    ]);
    $purchase = matchPurchaseFor($tenant);
    $line = pendingLineFor($purchase, [
        'raw_name' => 'HARINA 000 X 25 KG',
        'quantity_purchased' => 2,
        'unit_price' => 30000,
        'subtotal' => 60000,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
            'unit_cost' => '1200',
        ])
        ->assertRedirect();

    $ingredient->refresh();

    expect((float) $ingredient->cost_per_unit)->toBe(1200.0)
        ->and($ingredient->cost_per_package)->toBeNull();
});

test('el catálogo de vinculación indexa por tipo:id porque los ids de insumos y descartables colisionan', function () {
    [$user, $tenant] = ownerForMatch();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo]);
    $packaging = Packaging::factory()->for($tenant)->create([
        'subdivisions' => 100,
        'subdivision_label' => 'sobre',
    ]);
    $purchase = matchPurchaseFor($tenant);
    pendingLineFor($purchase);

    $html = $this->actingAs($user)->get(route('purchases.match', $purchase))->assertOk()->getContent();
    $catalog = str($html)->after('window.MATCH_CATALOG = ')->before('</script>')->toString();

    expect($catalog)->toContain("ingredient:{$ingredient->id}")
        ->and($catalog)->toContain("packaging:{$packaging->id}")
        ->and($catalog)->toContain('"subdivisions":100')
        ->and($catalog)->toContain('sobre');
});

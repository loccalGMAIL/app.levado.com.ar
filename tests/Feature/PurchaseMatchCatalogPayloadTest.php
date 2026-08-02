<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

/**
 * La pantalla de vincular repetía el catálogo entero dentro del <select> de
 * cada renglón: una factura de 50 líneas con 300 ítems mandaba 15.000 <option>
 * y montaba 50 TomSelect al cargar, que es lo que trababa la pantalla.
 *
 * Ahora el catálogo viaja una sola vez, en un <template> compartido.
 */
function ownerForCatalogPayload(): array
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

function purchaseWithPendingLines(Tenant $tenant, int $lines): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-08-01',
    ]);

    foreach (range(1, $lines) as $n) {
        $purchase->lines()->create([
            'raw_name' => "RENGLON {$n}",
            'quantity_purchased' => 1,
            'purchase_unit' => Unit::Kilogramo->value,
            'unit_price' => 100,
            'subtotal' => 100,
        ]);
    }

    return $purchase;
}

test('el catálogo se emite una sola vez, no una por renglón', function () {
    [$user, $tenant] = ownerForCatalogPayload();

    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000']);
    $packaging = Packaging::factory()->for($tenant)->create(['name' => 'Caja chica']);
    $purchase = purchaseWithPendingLines($tenant, 6);

    $html = $this->actingAs($user)
        ->get(route('purchases.match', $purchase))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, "value=\"ingredient:{$ingredient->id}\""))->toBe(1)
        ->and(substr_count($html, "value=\"packaging:{$packaging->id}\""))->toBe(1)
        ->and(substr_count($html, 'id="match-catalog-options"'))->toBe(1);
});

test('el renglón ya vinculado muestra su ítem antes de desplegar el catálogo', function () {
    [$user, $tenant] = ownerForCatalogPayload();

    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Levadura fresca']);
    $purchase = purchaseWithPendingLines($tenant, 1);
    $purchase->lines()->first()->update([
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->actingAs($user)
        ->get(route('purchases.match', $purchase))
        ->assertOk()
        // La opción elegida viaja en el select del renglón para que se vea
        // sin JS; el resto del catálogo lo agrega upgradeMatchSelect().
        ->assertSee("<option value=\"ingredient:{$ingredient->id}\" selected>Levadura fresca</option>", false);
});

test('el renglón de consumo personal conserva su opción', function () {
    [$user, $tenant] = ownerForCatalogPayload();

    Ingredient::factory()->for($tenant)->create();
    $purchase = purchaseWithPendingLines($tenant, 1);
    $purchase->lines()->first()->update(['excluded_at' => now()]);

    $this->actingAs($user)
        ->get(route('purchases.match', $purchase))
        ->assertOk()
        ->assertSee('<option value="excluded" selected>Consumo personal</option>', false);
});

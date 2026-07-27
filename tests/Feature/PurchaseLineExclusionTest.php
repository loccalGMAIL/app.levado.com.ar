<?php

use App\Enums\NotificationType;
use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\NotificationService;

function userForExclusion(TenantUserRole $role): array
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

function ownerForExclusion(): array
{
    return userForExclusion(TenantUserRole::Owner);
}

function exclusionPurchaseFor(Tenant $tenant): Purchase
{
    $supplier = Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-20']);
}

function exclusionLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'CERVEZA X 6',
        'quantity_purchased' => 1,
        'purchase_unit' => 'u',
        'unit_price' => 8000,
        'subtotal' => 8000,
    ], $overrides));
}

// --- Marcar como consumo personal ---

test('marcar un renglón como consumo personal lo excluye sin tocar el catálogo', function () {
    [$user, $tenant] = ownerForExclusion();
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => 'excluded',
            'exclusion_note' => 'Asado del domingo',
        ])
        ->assertRedirect();

    $line->refresh();

    expect($line->isExcluded())->toBeTrue()
        ->and($line->isResolved())->toBeTrue()
        ->and($line->isPending())->toBeFalse()
        ->and($line->exclusion_note)->toBe('Asado del domingo')
        ->and($line->purchaseable_id)->toBeNull()
        ->and($line->purchaseable_type)->toBeNull()
        ->and($line->cost_applied_at)->toBeNull();
});

test('la nota del consumo personal es opcional', function () {
    [$user, $tenant] = ownerForExclusion();
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => 'excluded'])
        ->assertRedirect();

    expect($line->refresh()->isExcluded())->toBeTrue()
        ->and($line->exclusion_note)->toBeNull();
});

test('una nota de más de 255 caracteres se rechaza', function () {
    [$user, $tenant] = ownerForExclusion();
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => 'excluded',
            'exclusion_note' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors('exclusion_note');

    expect($line->refresh()->isExcluded())->toBeFalse();
});

// --- Volver atrás ---

test('reasociar un renglón excluido a un ítem del catálogo limpia excluded_at', function () {
    [$user, $tenant] = ownerForExclusion();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'u']);
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase, ['excluded_at' => now(), 'exclusion_note' => 'personal']);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
        ])
        ->assertRedirect();

    $line->refresh();

    expect($line->isExcluded())->toBeFalse()
        ->and($line->exclusion_note)->toBeNull()
        ->and($line->purchaseable_id)->toBe($ingredient->id)
        ->and($line->isApplied())->toBeTrue();
});

test('marcar sin asociar un renglón excluido lo devuelve a pendiente', function () {
    [$user, $tenant] = ownerForExclusion();
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase, ['excluded_at' => now(), 'exclusion_note' => 'personal']);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => ''])
        ->assertRedirect();

    $line->refresh();

    expect($line->isExcluded())->toBeFalse()
        ->and($line->exclusion_note)->toBeNull()
        ->and($line->isPending())->toBeTrue();
});

// --- Aplicación masiva ---

test('aplicar las sugerencias de la IA no toca los renglones excluidos', function () {
    [$user, $tenant] = ownerForExclusion();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'u', 'cost_per_unit' => 100]);
    $purchase = exclusionPurchaseFor($tenant);

    // Excluido pero con un match viejo colgando: applyLineSuggestions no debe agarrarlo.
    $excluded = exclusionLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'excluded_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect($excluded->refresh()->isApplied())->toBeFalse()
        ->and($excluded->isExcluded())->toBeTrue()
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(100.0);
});

// --- Contadores ---

test('una factura con 2 renglones aplicados y 1 excluido se reporta como resuelta', function () {
    [$user, $tenant] = ownerForExclusion();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = exclusionPurchaseFor($tenant);

    exclusionLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'cost_applied_at' => now(),
    ]);
    exclusionLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'cost_applied_at' => now(),
    ]);
    exclusionLineFor($purchase, ['excluded_at' => now()]);

    $this->actingAs($user)
        ->get(route('purchases.match', $purchase))
        ->assertOk()
        ->assertSee('3<span class="text-masa-madre font-normal text-base">/3</span>', escape: false)
        ->assertSee('text-green-600', escape: false)
        ->assertSee('No queda ningún renglón por resolver')
        ->assertDontSee('Los renglones pendientes no actualizan ningún costo');

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('No queda ningún renglón por resolver');
});

test('un renglón excluido no alcanza para pintar de verde una factura con pendientes', function () {
    [$user, $tenant] = ownerForExclusion();
    $purchase = exclusionPurchaseFor($tenant);

    exclusionLineFor($purchase, ['excluded_at' => now()]);
    exclusionLineFor($purchase);

    $this->actingAs($user)
        ->get(route('purchases.match', $purchase))
        ->assertOk()
        ->assertDontSee('No queda ningún renglón por resolver')
        ->assertSee('1<span class="text-masa-madre font-normal text-base">/2</span>', escape: false);

    $this->actingAs($user)
        ->get(route('purchases.index'))
        ->assertOk()
        ->assertSee('1 renglón(es) sin resolver');
});

test('la alerta de compra sin imputar ignora los renglones de consumo personal', function () {
    [, $tenant] = ownerForExclusion();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = exclusionPurchaseFor($tenant);

    exclusionLineFor($purchase, [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'cost_applied_at' => now(),
    ]);
    $personal = exclusionLineFor($purchase);

    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::UnappliedPurchase->value)->active()->count())->toBe(1);

    $personal->update(['excluded_at' => now()]);
    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::UnappliedPurchase->value)->active()->count())->toBe(0);
});

// --- Permisos y aislamiento ---

test('un viewer no puede marcar un renglón como consumo personal', function () {
    [$user, $tenant] = userForExclusion(TenantUserRole::Viewer);
    $purchase = exclusionPurchaseFor($tenant);
    $line = exclusionLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => 'excluded'])
        ->assertForbidden();

    expect($line->refresh()->isExcluded())->toBeFalse();
});

test('excluir un renglón de otro negocio devuelve 404', function () {
    [$user] = ownerForExclusion();
    [, $otherTenant] = ownerForExclusion();
    $otherPurchase = exclusionPurchaseFor($otherTenant);
    $otherLine = exclusionLineFor($otherPurchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$otherPurchase, $otherLine]), ['match' => 'excluded'])
        ->assertNotFound();

    expect(PurchaseLine::withoutGlobalScope('tenant')->find($otherLine->id)->isExcluded())->toBeFalse();
});

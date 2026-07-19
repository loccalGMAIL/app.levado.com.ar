<?php

use App\Enums\CatalogItemType;
use App\Enums\NotificationType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PurchaseLineRecorder;

function ownerForAlerts(string $role = 'owner'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function makeLowStock(Tenant $tenant, float $quantity, float $min): Ingredient
{
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad->value]);
    StockLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $tenant->defaultLocation()->id,
        'stockable_type' => 'ingredient',
        'stockable_id' => $ingredient->id,
        'quantity' => $quantity,
        'min_quantity' => $min,
    ]);

    return $ingredient;
}

// ── Salto de costo (evento) ───────────────────────────────────────────────

test('una compra que sube el costo por encima del umbral registra un salto de costo', function () {
    [$user, $tenant] = ownerForAlerts();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-19']);

    $this->actingAs($user);
    app(PurchaseLineRecorder::class)->record($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1,
        'purchase_unit' => Unit::Unidad->value,
        'unit_price' => 150, // +50% sobre 100
    ]);

    $spike = Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->first();
    expect($spike)->not->toBeNull()
        ->and($spike->title)->toContain($ingredient->name);
});

test('una compra por debajo del umbral no registra salto de costo', function () {
    [$user, $tenant] = ownerForAlerts();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-19']);

    $this->actingAs($user);
    app(PurchaseLineRecorder::class)->record($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1,
        'purchase_unit' => Unit::Unidad->value,
        'unit_price' => 110, // +10%, bajo el umbral de 15%
    ]);

    expect(Notification::where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('el primer costo de un insumo no dispara salto de costo', function () {
    [$user, $tenant] = ownerForAlerts();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 0,
    ]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-19']);

    $this->actingAs($user);
    app(PurchaseLineRecorder::class)->record($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1,
        'purchase_unit' => Unit::Unidad->value,
        'unit_price' => 100,
    ]);

    expect(Notification::where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('el umbral configurado cambia cuándo dispara el salto de costo', function () {
    [$user, $tenant] = ownerForAlerts();
    $tenant->setSetting('alerts.cost_spike.threshold_pct', '60');
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-19']);

    $this->actingAs($user);
    app(PurchaseLineRecorder::class)->record($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1,
        'purchase_unit' => Unit::Unidad->value,
        'unit_price' => 150, // +50%, ahora bajo el umbral de 60%
    ]);

    expect(Notification::where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

// ── Stock bajo (estado, reconciliable) ────────────────────────────────────

test('sync crea una alerta de stock bajo y la resuelve al recuperarse', function () {
    [, $tenant] = ownerForAlerts();
    $ingredient = makeLowStock($tenant, quantity: 1, min: 5);
    $service = app(NotificationService::class);

    $service->syncStateAlerts($tenant);
    $alert = Notification::where('type', NotificationType::LowStock->value)->active()->first();
    expect($alert)->not->toBeNull()
        ->and($alert->title)->toContain($ingredient->name);

    // Idempotente: no duplica.
    $service->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::LowStock->value)->active()->count())->toBe(1);

    // Al reponer stock, se resuelve.
    StockLevel::where('stockable_id', $ingredient->id)->update(['quantity' => 10]);
    $service->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::LowStock->value)->active()->count())->toBe(0);
});

// ── Costo desactualizado (estado) ─────────────────────────────────────────

test('sync crea una alerta de costo desactualizado según los días configurados', function () {
    [, $tenant] = ownerForAlerts();
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => true]);
    $ingredient->priceLogs()->create(['cost_per_unit' => 100, 'recorded_at' => now()->subDays(100)]);

    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::StaleCost->value)->active()->count())->toBe(1);

    // Un costo reciente lo resuelve.
    $ingredient->priceLogs()->create(['cost_per_unit' => 120, 'recorded_at' => now()]);
    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::StaleCost->value)->active()->count())->toBe(0);
});

// ── Compras sin imputar (estado) ──────────────────────────────────────────

test('sync crea y resuelve la alerta de compra sin imputar', function () {
    [, $tenant] = ownerForAlerts();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-19']);
    $line = $purchase->lines()->create([
        'quantity_purchased' => 1,
        'purchase_unit' => Unit::Unidad->value,
        'unit_price' => 100,
        'iva_rate' => 0.21,
        'subtotal' => 100,
    ]);

    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::UnappliedPurchase->value)->active()->count())->toBe(1);

    $line->update(['cost_applied_at' => now()]);
    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::UnappliedPurchase->value)->active()->count())->toBe(0);
});

// ── Toggles ───────────────────────────────────────────────────────────────

test('apagar un tipo de alerta evita que se genere', function () {
    [, $tenant] = ownerForAlerts();
    $tenant->setSetting('alerts.low_stock.enabled', '0');
    makeLowStock($tenant, quantity: 1, min: 5);

    app(NotificationService::class)->syncStateAlerts($tenant);
    expect(Notification::where('type', NotificationType::LowStock->value)->active()->count())->toBe(0);
});

// ── Feed HTTP ─────────────────────────────────────────────────────────────

test('el dashboard muestra las alertas activas sin leer', function () {
    [$user, $tenant] = ownerForAlerts();
    $ingredient = makeLowStock($tenant, quantity: 0, min: 5);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Alertas')
        ->assertSee("Stock bajo: {$ingredient->name}");
});

test('marcar como leída y descartar una alerta', function () {
    [$user, $tenant] = ownerForAlerts();
    $notification = Notification::factory()->for($tenant)->create();

    $this->actingAs($user)->patch(route('notifications.read', $notification))->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)->delete(route('notifications.dismiss', $notification))->assertRedirect();
    expect($notification->fresh()->resolved_at)->not->toBeNull();
});

test('marcar todas como leídas', function () {
    [$user, $tenant] = ownerForAlerts();
    Notification::factory()->for($tenant)->count(3)->create();

    $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
    expect(Notification::where('tenant_id', $tenant->id)->unread()->count())->toBe(0);
});

// ── Configuración y permisos ──────────────────────────────────────────────

test('el owner puede editar la configuración de alertas y se persiste', function () {
    [$user, $tenant] = ownerForAlerts();

    $this->actingAs($user)->get(route('alerts.edit'))->assertOk();

    $this->actingAs($user)->patch(route('alerts.update'), [
        'low_stock_enabled' => '0',
        'cost_spike_threshold_pct' => '25',
        'stale_cost_days' => '30',
    ])->assertRedirect();

    expect($tenant->fresh()->getSetting('alerts.low_stock.enabled'))->toBe('0')
        ->and($tenant->fresh()->getSetting('alerts.cost_spike.threshold_pct'))->toBe('25')
        ->and($tenant->fresh()->getSetting('alerts.stale_cost.days'))->toBe('30');
});

test('un viewer no puede acceder a la configuración de alertas pero sí al feed', function () {
    [$user, $tenant] = ownerForAlerts('viewer');

    $this->actingAs($user)->get(route('alerts.edit'))->assertForbidden();
    $this->actingAs($user)->get(route('notifications.index'))->assertOk();
});

// ── Aislamiento ───────────────────────────────────────────────────────────

test('las alertas de un tenant no son visibles para otro', function () {
    [, $tenantA] = ownerForAlerts();
    [$userB, $tenantB] = ownerForAlerts();
    $notifA = Notification::factory()->for($tenantA)->create();

    $this->actingAs($userB)->patch(route('notifications.read', $notifA))->assertNotFound();
});

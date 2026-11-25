<?php

use App\Enums\LocationKind;
use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Notification;
use App\Models\StockMovement;
use App\Services\NotificationService;

test('la cámara se crea sola la primera vez que se pide', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $chamber = $tenant->proofingChamber();

    expect($chamber->kind)->toBe(LocationKind::ProofingChamber)
        ->and($chamber->is_default)->toBeFalse()
        ->and($chamber->name)->toBe('Cámara de leudado')
        ->and($tenant->fresh()->proofingChamber()->id)->toBe($chamber->id)
        ->and($tenant->locations()->where('kind', LocationKind::ProofingChamber->value)->count())->toBe(1);
});

test('la default nunca es la cámara aunque sea la única ubicación', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $chamber = Location::factory()->for($tenant)->proofingChamber()->create();

    $default = $tenant->defaultLocation();

    expect($default->id)->not->toBe($chamber->id)
        ->and($default->kind)->toBe(LocationKind::Store)
        ->and($default->is_default)->toBeTrue();
});

test('la cámara es de cada negocio', function () {
    [, $tenantA] = tenantUserAs(TenantUserRole::Owner);
    [, $tenantB] = tenantUserAs(TenantUserRole::Owner);

    $chamberA = $tenantA->proofingChamber();
    $chamberB = $tenantB->proofingChamber();

    expect($chamberA->id)->not->toBe($chamberB->id)
        ->and($chamberA->tenant_id)->toBe($tenantA->id)
        ->and($chamberB->tenant_id)->toBe($tenantB->id);
});

test('las compras siguen entrando a la sucursal default aunque exista la cámara', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $default = $tenant->defaultLocation();
    $tenant->proofingChamber();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr']);

    lineRecorder()->apply(stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $harina->id,
        'quantity_purchased' => 1,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
    ]));

    $movement = StockMovement::query()->where('stockable_id', $harina->id)->firstOrFail();
    expect($movement->location_id)->toBe($default->id);
});

test('no se puede crear una cámara como sucursal principal', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->defaultLocation();

    $this->actingAs($user)
        ->post(route('locations.store'), ['name' => 'Cámara', 'kind' => 'proofing_chamber', 'is_default' => 1])
        ->assertSessionHasErrors('kind');

    expect($tenant->locations()->where('kind', 'proofing_chamber')->exists())->toBeFalse();
});

test('no se puede crear una segunda cámara', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->proofingChamber();

    $this->actingAs($user)
        ->post(route('locations.store'), ['name' => 'Otra cámara', 'kind' => 'proofing_chamber'])
        ->assertSessionHasErrors('kind');
});

test('crear la cámara como primera ubicación no la vuelve principal', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('locations.store'), ['name' => 'Cámara', 'kind' => 'proofing_chamber'])
        ->assertSessionHasNoErrors();

    expect($tenant->locations()->firstOrFail()->is_default)->toBeFalse();
});

test('la principal no puede pasar a ser cámara', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $default = $tenant->defaultLocation();

    $this->actingAs($user)
        ->put(route('locations.update', $default), ['name' => $default->name, 'kind' => 'proofing_chamber', 'is_default' => 0])
        ->assertSessionHasErrors('kind');

    expect($default->fresh()->kind)->toBe(LocationKind::Store);
});

test('no se cambia el tipo de una ubicación con movimientos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->defaultLocation();
    $other = Location::factory()->for($tenant)->create();
    $product = doughProduct($tenant);
    stockService()->registerAdjustment($product, $other, 5, 'Carga', $user);

    $this->actingAs($user)
        ->put(route('locations.update', $other), ['name' => $other->name, 'kind' => 'proofing_chamber'])
        ->assertSessionHasErrors('kind');

    expect($other->fresh()->kind)->toBe(LocationKind::Store);
});

test('una ubicación sin movimientos sí cambia de tipo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->defaultLocation();
    $other = Location::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('locations.update', $other), ['name' => $other->name, 'kind' => 'proofing_chamber'])
        ->assertSessionHasNoErrors();

    expect($other->fresh()->kind)->toBe(LocationKind::ProofingChamber);
});

test('existencias muestra la cámara aparte de la sucursal', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = doughProduct($tenant, 'Medialunas');
    seedStock($product, 7, $user);
    seedChamber($product, 30, $user);
    $chamber = $tenant->proofingChamber();

    $this->actingAs($user)
        ->get(route('stock.index', ['type' => 'product']))
        ->assertOk()
        ->assertSee('Cámara de leudado')
        ->assertSee('7,00')
        ->assertDontSee('30,00');

    $this->actingAs($user)
        ->get(route('stock.index', ['type' => 'product', 'location_id' => $chamber->id]))
        ->assertOk()
        ->assertSee('cámara de leudado')
        ->assertSee('30,00')
        ->assertDontSee('7,00');
});

test('la alerta de stock negativo en cámara no pisa la de la sucursal', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = doughProduct($tenant, 'Medialunas');
    seedStock($product, -2, $user);
    seedChamber($product, -5, $user);

    app(NotificationService::class)->syncStateAlerts($tenant);

    $titles = Notification::query()->where('tenant_id', $tenant->id)->pluck('title')->all();
    expect($titles)->toContain('Stock bajo: Medialunas')
        ->and($titles)->toContain('Stock bajo: Medialunas (Cámara de leudado)');
});

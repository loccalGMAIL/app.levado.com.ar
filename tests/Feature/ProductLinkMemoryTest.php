<?php

use App\Enums\CatalogItemType;
use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ProductLinkMemory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function ownerForMemory(): array
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

function memoryPurchaseFor(Tenant $tenant, ?Supplier $supplier = null): Purchase
{
    $supplier ??= Supplier::factory()->for($tenant)->create();

    return $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-20',
    ]);
}

function memoryLineFor(Purchase $purchase, array $overrides = []): PurchaseLine
{
    return $purchase->lines()->create(array_merge([
        'raw_name' => 'HARINA 000 X 25 Kg',
        'quantity_purchased' => 10,
        'purchase_unit' => 'kg',
        'unit_price' => 614,
        'subtotal' => 6140,
    ], $overrides));
}

function rememberedLink(Tenant $tenant, Supplier $supplier, array $overrides = []): SupplierProductLink
{
    return SupplierProductLink::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'raw_name_normalized' => 'harina 000 x 25 kg',
        'raw_name_sample' => 'HARINA 000 X 25 Kg',
        'purchaseable_type' => CatalogItemType::Ingredient->value,
    ], $overrides));
}

/** Una compra escaneada con un solo renglón, tal como la manda la pantalla de revisión. */
function scanStorePayload(Supplier $supplier, array $lineOverrides = []): array
{
    return [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-08-14',
        'lines' => [array_merge([
            'include' => '1',
            'raw_name' => 'HARINA 000 X 25 Kg',
            'quantity_purchased' => '10',
            'purchase_unit' => 'kg',
            'unit_price' => '700',
        ], $lineOverrides)],
    ];
}

function storedLineFor(Tenant $tenant): PurchaseLine
{
    return $tenant->purchases()->latest('id')->firstOrFail()->lines()->firstOrFail();
}

// --- Aprendizaje ---

test('vincular un renglón a mano guarda el vínculo en la memoria', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $line = memoryLineFor($purchase);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "ingredient:{$ingredient->id}",
        ])
        ->assertRedirect();

    $link = SupplierProductLink::withoutGlobalScopes()->firstOrFail();

    expect($link->tenant_id)->toBe($tenant->id)
        ->and($link->supplier_id)->toBe($purchase->supplier_id)
        ->and($link->raw_name_normalized)->toBe('harina 000 x 25 kg')
        ->and($link->raw_name_sample)->toBe('HARINA 000 X 25 Kg')
        ->and($link->purchaseable_type)->toBe(CatalogItemType::Ingredient->value)
        ->and($link->purchaseable_id)->toBe($ingredient->id);
});

test('volver a vincular el mismo texto refresca el vínculo en vez de duplicarlo', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $first = memoryPurchaseFor($tenant, $supplier);
    $second = memoryPurchaseFor($tenant, $supplier);
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $azucar = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    $this->actingAs($user)->post(route('purchases.lines.match', [$first, memoryLineFor($first)]), [
        'match' => "ingredient:{$harina->id}",
    ])->assertRedirect();

    $this->actingAs($user)->post(route('purchases.lines.match', [$second, memoryLineFor($second)]), [
        'match' => "ingredient:{$azucar->id}",
    ])->assertRedirect();

    $links = SupplierProductLink::withoutGlobalScopes()->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->purchaseable_id)->toBe($azucar->id)
        ->and($links->first()->times_confirmed)->toBe(2);
});

test('aplicar las sugerencias en masa también enseña a la memoria', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    memoryLineFor($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(1);
});

// --- Olvido ---

test('desasociar un renglón olvida el vínculo', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $line = memoryLineFor($purchase);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => "ingredient:{$ingredient->id}",
    ])->assertRedirect();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(1);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => '',
    ])->assertRedirect();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(0);
});

test('marcar como consumo personal olvida el vínculo y no recuerda la exclusión', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $line = memoryLineFor($purchase);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => "ingredient:{$ingredient->id}",
    ])->assertRedirect();

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => 'excluded',
        'exclusion_note' => 'Asado del domingo',
    ])->assertRedirect();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(0)
        ->and($line->refresh()->isExcluded())->toBeTrue();
});

// --- Uso en la factura siguiente ---

test('una factura nueva del mismo proveedor llega con el renglón pre-vinculado', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    rememberedLink($tenant, $supplier, ['purchaseable_id' => $ingredient->id]);

    // La IA no sugirió nada esta vez.
    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($supplier, [
            'matched_type' => null,
            'matched_id' => null,
        ]))
        ->assertRedirect();

    expect(storedLineFor($tenant)->purchaseable_id)->toBe($ingredient->id)
        ->and(storedLineFor($tenant)->purchaseable_type)->toBe(CatalogItemType::Ingredient->value);
});

test('el renglón pre-vinculado queda PENDIENTE: no imputa costo ni mueve stock', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    rememberedLink($tenant, $supplier, ['purchaseable_id' => $ingredient->id]);

    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($supplier))
        ->assertRedirect();

    $line = storedLineFor($tenant);

    // Ésta es la garantía que se eligió al diseñar la feature: pre-seleccionar, nunca aplicar.
    expect($line->isApplied())->toBeFalse()
        ->and($line->isPending())->toBeTrue()
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(100.0);
});

test('la memoria pisa la sugerencia de la IA cuando difieren', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create(['name' => 'Molino San José']);
    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => 'kg']);
    $azucar = Ingredient::factory()->for($tenant)->create(['name' => 'Azucar impalpable', 'unit' => 'kg']);
    rememberedLink($tenant, $supplier, ['purchaseable_id' => $harina->id]);

    // La IA se equivoca y sugiere azúcar.
    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'supplier_name' => 'Molino San José',
                'invoice_number' => '0012-1',
                'invoice_date' => '2026-08-14',
                'total' => 6140,
                'lines' => [[
                    'raw_name' => 'HARINA 000 X 25 Kg',
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unit_price' => 614,
                    'matched_type' => 'ingredient',
                    'matched_id' => $azucar->id,
                ]],
            ])]],
        ]),
    ]);

    $this->actingAs($user)->post(route('purchases.scan'), [
        'invoice' => UploadedFile::fake()->image('factura.jpg', 800, 600),
    ])
        ->assertOk()
        ->assertSee('Se sugerirá:')
        ->assertSee('Harina 000')
        ->assertDontSee('Azucar impalpable');
});

// --- Alcance y aislamiento ---

test('la memoria no cruza proveedores', function () {
    [$user, $tenant] = ownerForMemory();
    $molino = Supplier::factory()->for($tenant)->create();
    $otro = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    rememberedLink($tenant, $molino, ['purchaseable_id' => $ingredient->id]);

    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($otro))
        ->assertRedirect();

    expect(storedLineFor($tenant)->purchaseable_id)->toBeNull();
});

test('un vínculo que apunta al catálogo de otro tenant no se pre-selecciona', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ajeno = Ingredient::factory()->for(Tenant::factory()->create())->create(['unit' => 'kg']);
    rememberedLink($tenant, $supplier, ['purchaseable_id' => $ajeno->id]);

    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($supplier))
        ->assertRedirect();

    expect(storedLineFor($tenant)->purchaseable_id)->toBeNull();
});

test('un vínculo recordado en otro negocio no se filtra', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);
    rememberedLink(Tenant::factory()->create(), $supplier, ['purchaseable_id' => $ingredient->id]);

    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($supplier))
        ->assertRedirect();

    expect(storedLineFor($tenant)->purchaseable_id)->toBeNull();
});

// --- Normalización ---

test('la normalización tolera mayúsculas, acentos y espacios de más', function () {
    $memory = app(ProductLinkMemory::class);

    expect($memory->fold('  HARINA   000  X 25 Kg '))->toBe('harina 000 x 25 kg')
        ->and($memory->fold('AZÚCAR IMPALPABLE'))->toBe('azucar impalpable')
        ->and($memory->fold("MANTECA\tX\n5 KG"))->toBe('manteca x 5 kg');
});

test('el mismo texto con distinto espaciado resuelve al mismo vínculo', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    rememberedLink($tenant, $supplier, ['purchaseable_id' => $ingredient->id]);

    $this->actingAs($user)
        ->post(route('purchases.scan.store'), scanStorePayload($supplier, [
            'raw_name' => 'Harina  000   x  25 KG',
        ]))
        ->assertRedirect();

    expect(storedLineFor($tenant)->purchaseable_id)->toBe($ingredient->id);
});

// --- Divisor ---

test('el divisor cargado a mano se recuerda junto al vínculo', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    // Comprada por unidad (bolsones), catálogo en kg: unidades incompatibles.
    $line = memoryLineFor($purchase, ['raw_name' => 'BOLSON HARINA', 'purchase_unit' => 'u']);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => "ingredient:{$ingredient->id}",
        'unit_cost' => '24.56',
        'pkg_qty' => '25',
    ])->assertRedirect();

    expect((float) SupplierProductLink::withoutGlobalScopes()->firstOrFail()->pkg_qty)->toBe(25.0);
});

test('el divisor recordado permite aplicar en masa un renglón de unidades incompatibles', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = memoryPurchaseFor($tenant, $supplier);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    // Sin "X 25 Kg" en el texto, parseDescPkgQty() no tiene de dónde deducir nada.
    rememberedLink($tenant, $supplier, [
        'raw_name_normalized' => 'bolson harina',
        'raw_name_sample' => 'BOLSON HARINA',
        'purchaseable_id' => $ingredient->id,
        'pkg_qty' => 25,
    ]);

    memoryLineFor($purchase, [
        'raw_name' => 'BOLSON HARINA',
        'purchase_unit' => 'u',
        'quantity_purchased' => 2,
        'unit_price' => 20000,
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    // 20.000 el bolsón ÷ 25 kg = 800 el kg.
    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(800.0)
        ->and($purchase->lines()->firstOrFail()->isApplied())->toBeTrue();
});

test('sin divisor recordado el mismo renglón se saltea, como antes', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    memoryLineFor($purchase, [
        'raw_name' => 'BOLSON HARINA',
        'purchase_unit' => 'u',
        'quantity_purchased' => 2,
        'unit_price' => 20000,
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(100.0)
        ->and($purchase->lines()->firstOrFail()->isApplied())->toBeFalse();
});

test('confirmar en masa no borra el divisor cargado a mano', function () {
    [$user, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = memoryPurchaseFor($tenant, $supplier);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    rememberedLink($tenant, $supplier, [
        'raw_name_normalized' => 'bolson harina',
        'raw_name_sample' => 'BOLSON HARINA',
        'purchaseable_id' => $ingredient->id,
        'pkg_qty' => 25,
    ]);

    memoryLineFor($purchase, [
        'raw_name' => 'BOLSON HARINA',
        'purchase_unit' => 'u',
        'quantity_purchased' => 2,
        'unit_price' => 20000,
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect((float) SupplierProductLink::withoutGlobalScopes()->firstOrFail()->pkg_qty)->toBe(25.0);
});

// --- Descartables ---

test('la memoria también recuerda descartables', function () {
    [$user, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 50]);
    $line = memoryLineFor($purchase, ['raw_name' => 'SOBRE KRAFT N4A', 'purchase_unit' => 'u']);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => "packaging:{$packaging->id}",
    ])->assertRedirect();

    $link = SupplierProductLink::withoutGlobalScopes()->firstOrFail();

    expect($link->purchaseable_type)->toBe(CatalogItemType::Packaging->value)
        ->and($link->purchaseable_id)->toBe($packaging->id);
});

// --- Backfill ---

test('el backfill siembra vínculos desde los renglones ya imputados', function () {
    [, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    memoryLineFor($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'cost_applied_at' => now(),
    ]);

    $this->artisan('purchases:backfill-product-links')->assertSuccessful();

    $link = SupplierProductLink::withoutGlobalScopes()->firstOrFail();

    expect($link->raw_name_normalized)->toBe('harina 000 x 25 kg')
        ->and($link->purchaseable_id)->toBe($ingredient->id)
        ->and($link->pkg_qty)->toBeNull();
});

test('el backfill ignora los renglones pendientes', function () {
    [, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);

    // Sugerido por la IA pero nunca confirmado.
    memoryLineFor($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
    ]);

    $this->artisan('purchases:backfill-product-links')->assertSuccessful();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(0);
});

test('ante textos repetidos el backfill se queda con la imputación más reciente', function () {
    [, $tenant] = ownerForMemory();
    $supplier = Supplier::factory()->for($tenant)->create();
    $viejo = memoryPurchaseFor($tenant, $supplier);
    $nuevo = memoryPurchaseFor($tenant, $supplier);
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);
    $azucar = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);

    memoryLineFor($viejo, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $harina->id,
        'cost_applied_at' => now()->subMonth(),
    ]);
    memoryLineFor($nuevo, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $azucar->id,
        'cost_applied_at' => now(),
    ]);

    $this->artisan('purchases:backfill-product-links')->assertSuccessful();

    $links = SupplierProductLink::withoutGlobalScopes()->get();

    expect($links)->toHaveCount(1)
        ->and($links->first()->purchaseable_id)->toBe($azucar->id);
});

test('el dry-run del backfill no escribe nada', function () {
    [, $tenant] = ownerForMemory();
    $purchase = memoryPurchaseFor($tenant);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);

    memoryLineFor($purchase, [
        'purchaseable_type' => CatalogItemType::Ingredient->value,
        'purchaseable_id' => $ingredient->id,
        'cost_applied_at' => now(),
    ]);

    $this->artisan('purchases:backfill-product-links', ['--dry-run' => true])->assertSuccessful();

    expect(SupplierProductLink::withoutGlobalScopes()->count())->toBe(0);
});

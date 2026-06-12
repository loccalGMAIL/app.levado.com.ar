<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

test('scan renders the review with detected lines', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => 'kg']);

    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'supplier_name' => 'Molino',
                'invoice_number' => '0012-1',
                'invoice_date' => '2026-05-14',
                'total' => 100,
                'lines' => [[
                    'raw_name' => 'HARINA X 25 Kg',
                    'quantity' => 200,
                    'unit' => 'u',
                    'unit_price' => 13891.40,
                    'matched_type' => 'ingredient',
                    'matched_id' => $ingredient->id,
                ]],
            ])]],
        ]),
    ]);

    $this->actingAs($user)->post(route('purchases.scan'), [
        'invoice' => UploadedFile::fake()->image('factura.jpg', 800, 600),
    ])
        ->assertOk()
        ->assertSee('Revisá lo que se leyó')
        ->assertSee('HARINA X 25 Kg')
        ->assertSee('Harina 000'); // suggestion hint
});

test('storing a scanned purchase captures lines without applying cost', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);

    $this->actingAs($user)->post(route('purchases.scan.store'), [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'lines' => [[
            'include' => '1',
            'raw_name' => 'HARINA X 25 Kg',
            'matched_type' => 'ingredient',
            'matched_id' => $ingredient->id,
            'quantity_purchased' => '5000',
            'purchase_unit' => 'kg',
            'unit_price' => '614',
        ]],
    ])->assertRedirect();

    $line = $tenant->purchases()->firstOrFail()->lines()->firstOrFail();

    expect($line->raw_name)->toBe('HARINA X 25 Kg')
        ->and($line->purchaseable_id)->toBe($ingredient->id)
        ->and($line->isApplied())->toBeFalse();

    // Cost must NOT have changed yet.
    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(100.0);
});

test('matching a captured line imputes its cost', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-05-14']);
    $line = $purchase->lines()->create([
        'raw_name' => 'HARINA X 25 Kg',
        'quantity_purchased' => 5000,
        'purchase_unit' => 'kg',
        'unit_price' => 614,
        'subtotal' => 3070000,
    ]);

    $this->actingAs($user)->post(route('purchases.lines.match', [$purchase, $line]), [
        'match' => "ingredient:{$ingredient->id}",
    ])->assertRedirect();

    $line->refresh();
    expect($line->isApplied())->toBeTrue()
        ->and($line->purchaseable_id)->toBe($ingredient->id);

    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(614.0); // kg == kg
});

test('the purchase detail page renders with captured lines', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => 'kg']);
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-05-14']);
    $purchase->lines()->create([
        'raw_name' => 'HARINA X 25 Kg',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 200,
        'purchase_unit' => 'u',
        'unit_price' => 13891.40,
        'subtotal' => 2778280,
    ]);

    $this->actingAs($user)->get(route('purchases.show', $purchase))
        ->assertOk()
        ->assertSee('HARINA X 25 Kg')
        ->assertSee('Renglones de la factura');
});

test('adding a manual line digitises it without applying cost', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-05-14']);

    $this->actingAs($user)->post(route('purchases.lines.store', $purchase), [
        'raw_name' => 'AZUCAR X 25kg',
        'quantity_purchased' => '5',
        'purchase_unit' => 'u',
        'unit_price' => '24733.25',
    ])->assertRedirect();

    $line = $purchase->lines()->firstOrFail();
    expect($line->raw_name)->toBe('AZUCAR X 25kg')
        ->and($line->isMatched())->toBeFalse()
        ->and($line->isApplied())->toBeFalse();
});

test('the purchases index shows the total according to the IVA setting', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'invoice_total' => 12100,
    ]);
    $purchase->lines()->create([
        'raw_name' => 'X', 'quantity_purchased' => 10, 'purchase_unit' => 'u', 'unit_price' => 1000, 'subtotal' => 10000,
    ]);

    $tenant->setSetting('purchase_price_includes_iva', '1');
    $this->actingAs($user)->get(route('purchases.index'))
        ->assertOk()->assertSee('12.100,00')->assertSee('(c/IVA)');

    $tenant->setSetting('purchase_price_includes_iva', '0');
    $this->actingAs($user)->get(route('purchases.index'))
        ->assertOk()->assertSee('10.000,00')->assertSee('(s/IVA)');
});

test('the invoice image is served through the app', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    Storage::disk('local')->put("purchases/{$tenant->id}/factura.jpg", 'imgbytes');
    $purchase = $tenant->purchases()->create([
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'invoice_image_path' => "purchases/{$tenant->id}/factura.jpg",
    ]);

    $this->actingAs($user)->get(route('purchases.invoice', $purchase))->assertOk();
});

test('the scan preview streams a tenant-owned image from the private disk', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    Storage::disk('local')->put("purchases/{$tenant->id}/draft.jpg", 'imgbytes');

    $this->actingAs($user)
        ->get(route('purchases.scan.preview', ['path' => "purchases/{$tenant->id}/draft.jpg"]))
        ->assertOk();
});

test('the scan preview 404s for an image owned by another tenant', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    [$otherUser, $otherTenant] = ownerForScan();
    Storage::disk('local')->put("purchases/{$otherTenant->id}/draft.jpg", 'imgbytes');

    $this->actingAs($user)
        ->get(route('purchases.scan.preview', ['path' => "purchases/{$otherTenant->id}/draft.jpg"]))
        ->assertNotFound();
});

test('the invoice route 404s when there is no image', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-05-14']);

    $this->actingAs($user)->get(route('purchases.invoice', $purchase))->assertNotFound();
});

test('apply-suggestions imputes all pending matched lines', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => 'kg', 'cost_per_unit' => 100]);
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-05-14']);
    $line = $purchase->lines()->create([
        'raw_name' => 'HARINA',
        'purchaseable_type' => 'ingredient',
        'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 10,
        'purchase_unit' => 'kg',
        'unit_price' => 500,
        'subtotal' => 5000,
    ]);

    $this->actingAs($user)->post(route('purchases.apply-suggestions', $purchase))->assertRedirect();

    expect($line->refresh()->isApplied())->toBeTrue()
        ->and((float) $ingredient->fresh()->cost_per_unit)->toBe(500.0);
});

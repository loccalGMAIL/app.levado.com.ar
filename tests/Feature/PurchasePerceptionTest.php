<?php

use App\Enums\PerceptionBase;
use App\Models\Ingredient;
use App\Models\PerceptionConcept;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: User, 1: Tenant, 2: Purchase}
 */
function purchaseWithLines(array $lines = [['subtotal' => 1000, 'iva_rate' => 0.21]]): array
{
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-13']);

    foreach ($lines as $line) {
        PurchaseLine::factory()->for($purchase)->create($line);
    }

    return [$user, $tenant, $purchase];
}

test('una percepción del catálogo copia el snapshot y calcula base × alícuota', function () {
    [$user, $tenant, $purchase] = purchaseWithLines();
    $concept = PerceptionConcept::factory()->for($tenant)->create([
        'type' => 'iibb', 'jurisdiction' => 'cordoba', 'name' => 'Perc. IIBB Córdoba', 'default_rate' => 3,
    ]);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'perception_concept_id' => $concept->id,
    ])->assertRedirect();

    $perception = $purchase->perceptions()->firstOrFail();

    expect($perception->type->value)->toBe('iibb')
        ->and($perception->jurisdiction->value)->toBe('cordoba')
        ->and($perception->description)->toBe('Perc. IIBB Córdoba')
        ->and((float) $perception->rate)->toBe(3.0)
        ->and((float) $perception->amount)->toBe(30.0)
        ->and($perception->amount_is_manual)->toBeFalse()
        ->and($perception->usesAutomaticBase())->toBeTrue();
});

test('la base automática es el neto gravado y deja afuera los renglones exentos', function () {
    [$user, , $purchase] = purchaseWithLines([
        ['subtotal' => 1000, 'iva_rate' => 0.21],
        ['subtotal' => 500, 'iva_rate' => 0],
    ]);

    expect($purchase->taxedNet())->toBe(1000.0);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc. IVA', 'rate' => 3,
    ]);

    expect((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(30.0);
});

test('sin renglones gravados la base cae al neto total', function () {
    [, , $purchase] = purchaseWithLines([['subtotal' => 800, 'iva_rate' => 0]]);

    expect($purchase->taxedNet())->toBe(800.0);
});

test('un importe escrito manda y no se recalcula al cambiar los renglones', function () {
    [$user, , $purchase] = purchaseWithLines();

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc. IIBB', 'rate' => 3, 'amount' => '31.55',
    ]);

    $perception = $purchase->perceptions()->firstOrFail();
    expect($perception->amount_is_manual)->toBeTrue()
        ->and((float) $perception->amount)->toBe(31.55);

    $this->actingAs($user)->post(route('purchases.lines.store', $purchase), [
        'raw_name' => 'OTRO', 'quantity_purchased' => 1, 'purchase_unit' => 'u', 'unit_price' => 1000, 'iva_rate' => 0.21,
    ])->assertRedirect();

    expect((float) $perception->fresh()->amount)->toBe(31.55);
});

test('una percepción automática se recalcula al agregar, editar o borrar renglones', function () {
    [$user, , $purchase] = purchaseWithLines();
    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc. IVA', 'rate' => 3,
    ]);
    $perception = $purchase->perceptions()->firstOrFail();

    $this->actingAs($user)->post(route('purchases.lines.store', $purchase), [
        'raw_name' => 'OTRO', 'quantity_purchased' => 1, 'purchase_unit' => 'u', 'unit_price' => 1000, 'iva_rate' => 0.21,
    ]);
    expect((float) $perception->fresh()->amount)->toBe(60.0);

    $line = $purchase->lines()->latest('id')->firstOrFail();
    $this->actingAs($user)->patchJson(route('purchases.lines.price.update', [$purchase, $line]), ['unit_price' => 2000])
        ->assertOk()
        ->assertJsonPath('total_percepcion', 90)
        ->assertJsonPath('perceptions.0.amount', 90);

    $this->actingAs($user)->delete(route('purchases.lines.destroy', [$purchase, $line]))->assertRedirect();
    expect((float) $perception->fresh()->amount)->toBe(30.0);
});

test('una compra admite varias percepciones de distintas provincias', function () {
    [$user, , $purchase] = purchaseWithLines();

    foreach ([['buenos_aires', 3], ['santa_fe', 1.5]] as [$province, $rate]) {
        $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
            'type' => 'iibb', 'jurisdiction' => $province, 'description' => "IIBB {$province}", 'rate' => $rate,
        ])->assertSessionHasNoErrors();
    }

    expect($purchase->perceptions()->count())->toBe(2)
        ->and($purchase->fresh()->perceptionsTotal())->toBe(45.0);
});

test('una compra sin factura admite cero percepciones y el total es el neto', function () {
    [$user, , $purchase] = purchaseWithLines([['subtotal' => 700, 'iva_rate' => 0]]);

    $this->actingAs($user)->get(route('purchases.show', $purchase))
        ->assertOk()
        ->assertSee('no tiene percepciones');

    expect($purchase->perceptionsTotal())->toBe(0.0);
});

test('sin concepto hace falta descripción y un valor', function () {
    [$user, , $purchase] = purchaseWithLines();

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [])
        ->assertSessionHasErrors(['description', 'amount']);
});

test('editar una percepción y volverla automática recalcula el importe', function () {
    [$user, , $purchase] = purchaseWithLines();
    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc.', 'rate' => 3, 'amount' => 99,
    ]);
    $perception = $purchase->perceptions()->firstOrFail();

    $this->actingAs($user)->patch(route('purchases.perceptions.update', [$purchase, $perception]), [
        'description' => 'Perc.', 'rate' => 5,
    ])->assertRedirect();

    expect((float) $perception->fresh()->amount)->toBe(50.0)
        ->and($perception->fresh()->amount_is_manual)->toBeFalse();
});

test('no se puede operar sobre una percepción de otra compra ni de otro negocio', function () {
    [$user, , $purchase] = purchaseWithLines();
    [, , $foreign] = purchaseWithLines();
    $foreignPerception = $foreign->perceptions()->create(['type' => 'otro', 'description' => 'x', 'amount' => 1]);

    $this->actingAs($user)->delete(route('purchases.perceptions.destroy', [$purchase, $foreignPerception]))->assertNotFound();
    $this->actingAs($user)->post(route('purchases.perceptions.store', $foreign), ['description' => 'x', 'rate' => 1])->assertNotFound();

    expect($foreignPerception->fresh())->not->toBeNull();
});

test('el concepto de otro negocio no se puede usar', function () {
    [$user, , $purchase] = purchaseWithLines();
    $foreignConcept = PerceptionConcept::factory()->for(Tenant::factory()->create())->create(['name' => 'Ajeno']);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'perception_concept_id' => $foreignConcept->id, 'description' => 'Propia', 'rate' => 3,
    ]);

    $perception = $purchase->perceptions()->firstOrFail();
    expect($perception->perception_concept_id)->toBeNull()
        ->and($perception->description)->toBe('Propia');
});

test('el índice suma IVA y percepciones en el total y ordena por él', function () {
    [$user, $tenant, $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);
    $purchase->perceptions()->create(['type' => 'otro', 'description' => 'P', 'rate' => 3, 'amount' => 30]);

    $cheaper = $tenant->purchases()->create(['supplier_id' => $purchase->supplier_id, 'invoice_date' => '2026-07-14']);
    PurchaseLine::factory()->for($cheaper)->create(['subtotal' => 1100, 'iva_rate' => 0.21]);

    $response = $this->actingAs($user)->get(route('purchases.index', ['sort' => 'total', 'dir' => 'asc']))->assertOk();

    // 1000 × 1,21 + 30 = 1.240 contra 1100 × 1,21 = 1.331.
    $response->assertSee('1.240,00')->assertSeeInOrder(['1.240,00', '1.331,00']);
});

// --- Escaneo ---

test('el escaneo lee las percepciones, descarta tipos inválidos y las matchea con el catálogo', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);
    $concept = PerceptionConcept::factory()->for($tenant)->create([
        'type' => 'iibb', 'jurisdiction' => 'buenos_aires', 'name' => 'Perc. IIBB Bs.As. habitual', 'default_rate' => 3,
    ]);

    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
        'supplier_name' => 'Molino', 'invoice_number' => '1', 'invoice_date' => '2026-05-14', 'total' => 1300, 'lines' => [],
        'perceptions' => [
            ['type' => 'iibb', 'jurisdiction' => 'buenos_aires', 'description' => 'Perc. IIBB Bs.As.', 'rate' => 3, 'amount' => 30.5],
            ['type' => 'invento', 'jurisdiction' => 'marte', 'description' => 'Raro', 'amount' => 5],
            ['type' => 'iva', 'jurisdiction' => 'cordoba', 'description' => 'Perc. IVA', 'amount' => 12],
            ['type' => 'otro', 'description' => 'Sin números'],
        ],
    ])]]])]);

    $response = $this->actingAs($user)->post(route('purchases.scan'), [
        'invoice' => UploadedFile::fake()->image('factura.jpg', 800, 600),
    ])->assertOk();

    $perceptions = collect($response->viewData('draft')['perceptions']);

    expect($perceptions)->toHaveCount(3)
        ->and($perceptions[0]['perception_concept_id'])->toBe($concept->id)
        ->and($perceptions[0]['amount'])->toBe(30.5)
        ->and($perceptions[1]['type'])->toBe('otro')
        ->and($perceptions[1]['jurisdiction'])->toBeNull()
        ->and($perceptions[2]['type'])->toBe('iva')
        ->and($perceptions[2]['jurisdiction'])->toBeNull()
        ->and($perceptions[2]['perception_concept_id'])->toBeNull();
});

test('guardar el escaneo crea las percepciones: el importe leído queda manual y sin importe se calcula', function () {
    [$user, $tenant] = ownerForScan();
    $supplier = Supplier::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('purchases.scan.store'), [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'lines' => [[
            'include' => '1', 'raw_name' => 'HARINA', 'quantity_purchased' => '10', 'purchase_unit' => 'kg',
            'unit_price' => '100', 'iva_rate' => '0.21',
        ]],
        'perceptions' => [
            ['type' => 'iibb', 'jurisdiction' => 'buenos_aires', 'description' => 'IIBB', 'rate' => '3', 'amount' => '29.99'],
            ['type' => 'iva', 'description' => 'Perc. IVA', 'rate' => '2', 'amount' => ''],
            ['type' => 'otro', 'description' => '', 'rate' => '', 'amount' => ''],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $perceptions = $tenant->purchases()->firstOrFail()->perceptions()->orderBy('id')->get();

    expect($perceptions)->toHaveCount(2)
        ->and((float) $perceptions[0]->amount)->toBe(29.99)
        ->and($perceptions[0]->amount_is_manual)->toBeTrue()
        ->and((float) $perceptions[1]->amount)->toBe(20.0)
        ->and($perceptions[1]->amount_is_manual)->toBeFalse();
});

// --- Base de cálculo por concepto ---

test('baseFor da neto gravado, neto total y total con IVA', function () {
    [, , $purchase] = purchaseWithLines([
        ['subtotal' => 1000, 'iva_rate' => 0.21],
        ['subtotal' => 500, 'iva_rate' => 0],
    ]);

    expect($purchase->baseFor(PerceptionBase::NetTaxed))->toBe(1000.0)
        ->and($purchase->baseFor(PerceptionBase::NetTotal))->toBe(1500.0)
        ->and($purchase->baseFor(PerceptionBase::Gross))->toBe(1710.0);
});

test('un concepto sobre total con IVA calcula con esa base y la conserva al recalcular', function () {
    [$user, $tenant, $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);
    $concept = PerceptionConcept::factory()->for($tenant)->create(['default_rate' => 3, 'base_type' => 'gross']);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'perception_concept_id' => $concept->id,
    ])->assertRedirect();

    $perception = $purchase->perceptions()->firstOrFail();
    expect($perception->base_type)->toBe(PerceptionBase::Gross)
        ->and((float) $perception->amount)->toBe(36.3); // 1.210 × 3%

    $this->actingAs($user)->post(route('purchases.lines.store', $purchase), [
        'raw_name' => 'OTRO', 'quantity_purchased' => 1, 'purchase_unit' => 'u', 'unit_price' => 1000, 'iva_rate' => 0.21,
    ]);

    expect((float) $perception->fresh()->amount)->toBe(72.6); // 2.420 × 3%
});

test('sin concepto ni base elegida la base es el neto gravado', function () {
    [$user, , $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), ['description' => 'Perc.', 'rate' => 3]);

    expect($purchase->perceptions()->firstOrFail()->base_type)->toBe(PerceptionBase::NetTaxed);
});

test('la base que elige el usuario en el formulario manda sobre la del concepto', function () {
    [$user, $tenant, $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);
    $concept = PerceptionConcept::factory()->for($tenant)->create(['default_rate' => 3, 'base_type' => 'gross']);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'perception_concept_id' => $concept->id, 'base_type' => 'net_total',
    ]);

    expect($purchase->perceptions()->firstOrFail()->base_type)->toBe(PerceptionBase::NetTotal)
        ->and((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(30.0);
});

test('una base escrita a mano gana sobre el tipo de base', function () {
    [$user, , $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'Perc.', 'rate' => 3, 'base_type' => 'gross', 'base_amount' => 200,
    ]);

    expect((float) $purchase->perceptions()->firstOrFail()->amount)->toBe(6.0);
});

test('cambiar la base del concepto no modifica las percepciones ya cargadas', function () {
    [$user, $tenant, $purchase] = purchaseWithLines([['subtotal' => 1000, 'iva_rate' => 0.21]]);
    $concept = PerceptionConcept::factory()->for($tenant)->create(['default_rate' => 3, 'base_type' => 'net_taxed']);
    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), ['perception_concept_id' => $concept->id]);

    $this->actingAs($user)->put(route('perception-concepts.update', $concept), [
        'type' => 'iva', 'name' => $concept->name, 'base_type' => 'gross',
    ])->assertRedirect();

    $perception = $purchase->perceptions()->firstOrFail();
    expect($concept->fresh()->base_type)->toBe(PerceptionBase::Gross)
        ->and($perception->base_type)->toBe(PerceptionBase::NetTaxed)
        ->and((float) $perception->amount)->toBe(30.0);
});

test('una base inválida se rechaza', function () {
    [$user, , $purchase] = purchaseWithLines();

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), [
        'description' => 'x', 'rate' => 1, 'base_type' => 'inventada',
    ])->assertSessionHasErrors('base_type');
    $this->actingAs($user)->post(route('perception-concepts.store'), [
        'type' => 'iva', 'name' => 'x', 'base_type' => 'inventada',
    ])->assertSessionHasErrors('base_type');
});

test('el escaneo propone la base del concepto y guardarlo la conserva', function () {
    Storage::fake('local');
    [$user, $tenant] = ownerForScan();
    Ingredient::factory()->for($tenant)->create(['unit' => 'kg']);
    PerceptionConcept::factory()->for($tenant)->create(['type' => 'iva', 'default_rate' => 3, 'base_type' => 'gross']);

    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
        'supplier_name' => 'Molino', 'invoice_number' => '1', 'invoice_date' => '2026-05-14', 'total' => 1, 'lines' => [],
        'perceptions' => [['type' => 'iva', 'description' => 'Perc. IVA', 'rate' => 3, 'amount' => 36.3]],
    ])]]])]);

    $draft = $this->actingAs($user)->post(route('purchases.scan'), [
        'invoice' => UploadedFile::fake()->image('factura.jpg', 800, 600),
    ])->assertOk()->viewData('draft');

    expect($draft['perceptions'][0]['base_type'])->toBe('gross');

    $supplier = Supplier::factory()->for($tenant)->create();
    $this->actingAs($user)->post(route('purchases.scan.store'), [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-05-14',
        'lines' => [['include' => '1', 'raw_name' => 'H', 'quantity_purchased' => '1', 'purchase_unit' => 'kg', 'unit_price' => '1000', 'iva_rate' => '0.21']],
        'perceptions' => [['description' => 'Perc. IVA', 'rate' => '3', 'base_type' => 'gross', 'amount' => '']],
    ])->assertRedirect();

    $perception = $tenant->purchases()->firstOrFail()->perceptions()->firstOrFail();
    expect($perception->base_type)->toBe(PerceptionBase::Gross)
        ->and((float) $perception->amount)->toBe(36.3);
});

test('el modal de percepción ya no ofrece base imponible y el de concepto no lista el catálogo', function () {
    [$user, $tenant, $purchase] = purchaseWithLines();
    $concept = PerceptionConcept::factory()->for($tenant)->create(['name' => 'Perc. Existente']);

    $html = $this->actingAs($user)->get(route('purchases.show', $purchase))->assertOk()->getContent();

    expect($html)->not->toContain('name="base_amount"')
        ->and($html)->not->toContain('perception-concepts/'.$concept->id)
        ->and($html)->toContain('Nueva percepción')
        ->and($html)->toContain('perceptionConceptForm');
});

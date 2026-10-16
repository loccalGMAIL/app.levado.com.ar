<?php

use App\Enums\PerceptionType;
use App\Models\PerceptionConcept;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\Tenant;

test('crea un concepto de IIBB con su provincia', function () {
    [$user, $tenant] = ownerForScan();

    $this->actingAs($user)->post(route('perception-concepts.store'), [
        'type' => 'iibb',
        'jurisdiction' => 'buenos_aires',
        'name' => 'Perc. IIBB Buenos Aires',
        'default_rate' => '3',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $concept = $tenant->perceptionConcepts()->firstOrFail();

    expect($concept->type)->toBe(PerceptionType::Iibb)
        ->and($concept->jurisdiction->value)->toBe('buenos_aires')
        ->and((float) $concept->default_rate)->toBe(3.0)
        ->and($concept->is_active)->toBeTrue();
});

test('IIBB exige provincia', function () {
    [$user] = ownerForScan();

    $this->actingAs($user)->post(route('perception-concepts.store'), [
        'type' => 'iibb',
        'name' => 'Perc. IIBB',
    ])->assertSessionHasErrors('jurisdiction');
});

test('un tipo nacional descarta la provincia que llegue', function () {
    [$user, $tenant] = ownerForScan();

    $this->actingAs($user)->post(route('perception-concepts.store'), [
        'type' => 'iva',
        'jurisdiction' => 'cordoba',
        'name' => 'Perc. IVA',
    ])->assertSessionHasNoErrors();

    expect($tenant->perceptionConcepts()->firstOrFail()->jurisdiction)->toBeNull();
});

test('el alta por JSON devuelve el concepto para usarlo al instante', function () {
    [$user] = ownerForScan();

    $this->actingAs($user)->postJson(route('perception-concepts.store'), [
        'type' => 'ganancias',
        'name' => 'Perc. Ganancias',
        'default_rate' => '2',
    ])->assertCreated()->assertJsonPath('type', 'ganancias')->assertJsonPath('default_rate', 2);
});

test('actualiza y desactiva un concepto', function () {
    [$user, $tenant] = ownerForScan();
    $concept = PerceptionConcept::factory()->for($tenant)->create(['name' => 'Viejo']);

    $this->actingAs($user)->put(route('perception-concepts.update', $concept), [
        'type' => 'iva',
        'name' => 'Nuevo',
        'default_rate' => '3',
        'is_active' => '0',
    ])->assertRedirect();

    expect($concept->fresh()->name)->toBe('Nuevo')
        ->and($concept->fresh()->is_active)->toBeFalse();
});

test('no se puede tocar un concepto de otro negocio', function () {
    [$user] = ownerForScan();
    $other = Tenant::factory()->create();
    $concept = PerceptionConcept::factory()->for($other)->create();

    $this->actingAs($user)->put(route('perception-concepts.update', $concept), [
        'type' => 'iva',
        'name' => 'Hackeado',
    ])->assertNotFound();

    $this->actingAs($user)->delete(route('perception-concepts.destroy', $concept))->assertNotFound();

    expect($concept->fresh()->name)->not->toBe('Hackeado');
});

test('borrar un concepto no altera las percepciones ya cargadas', function () {
    [$user, $tenant] = ownerForScan();
    $concept = PerceptionConcept::factory()->for($tenant)->create(['name' => 'Perc. IVA 3%']);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-13']);
    $perception = $purchase->perceptions()->create([
        'perception_concept_id' => $concept->id,
        'type' => 'iva',
        'description' => 'Perc. IVA 3%',
        'rate' => 3,
        'amount' => 30,
    ]);

    $this->actingAs($user)->delete(route('perception-concepts.destroy', $concept))->assertRedirect();

    $perception->refresh();
    expect($perception->perception_concept_id)->toBeNull()
        ->and($perception->description)->toBe('Perc. IVA 3%')
        ->and((float) $perception->amount)->toBe(30.0);
});

// --- Administración del catálogo (por JSON, desde el modal «Administrar») ---

test('editar y desactivar un concepto por JSON devuelve su forma completa', function () {
    [$user, $tenant] = ownerForScan();
    $concept = PerceptionConcept::factory()->for($tenant)->create([
        'type' => 'iibb', 'jurisdiction' => 'cordoba', 'name' => 'Viejo', 'default_rate' => 3,
    ]);

    $this->actingAs($user)->putJson(route('perception-concepts.update', $concept), [
        'type' => 'iibb', 'jurisdiction' => 'cordoba', 'name' => 'Perc. IIBB Córdoba', 'default_rate' => 1.5,
        'base_type' => 'net_total', 'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('name', 'Perc. IIBB Córdoba')
        ->assertJsonPath('default_rate', 1.5)
        ->assertJsonPath('base_type', 'net_total')
        ->assertJsonPath('is_active', false)
        ->assertJsonPath('jurisdiction_label', 'Córdoba')
        ->assertJsonPath('type_label', 'Percepción de IIBB');

    expect($concept->fresh()->is_active)->toBeFalse();
});

test('borrar un concepto por JSON conserva alícuota, base e importe de las compras', function () {
    [$user, $tenant] = ownerForScan();
    $concept = PerceptionConcept::factory()->for($tenant)->create(['name' => 'Perc. IVA', 'default_rate' => 3, 'base_type' => 'gross']);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-13']);
    PurchaseLine::factory()->for($purchase)->create(['subtotal' => 1000, 'iva_rate' => 0.21]);

    $this->actingAs($user)->post(route('purchases.perceptions.store', $purchase), ['perception_concept_id' => $concept->id]);

    $this->actingAs($user)->deleteJson(route('perception-concepts.destroy', $concept))
        ->assertOk()->assertJsonPath('id', $concept->id);

    $perception = $purchase->perceptions()->firstOrFail();
    expect(PerceptionConcept::find($concept->id))->toBeNull()
        ->and($perception->perception_concept_id)->toBeNull()
        ->and($perception->description)->toBe('Perc. IVA')
        ->and((float) $perception->rate)->toBe(3.0)
        ->and($perception->base_type->value)->toBe('gross')
        ->and((float) $perception->amount)->toBe(36.3);
});

test('el selector de la compra muestra alícuota y base, y el índice trae el administrador', function () {
    [$user, $tenant] = ownerForScan();
    PerceptionConcept::factory()->for($tenant)->create([
        'name' => 'Perc. IIBB Bs.As.', 'type' => 'iibb', 'jurisdiction' => 'buenos_aires', 'default_rate' => 1.5, 'base_type' => 'net_taxed',
    ]);
    $supplier = Supplier::factory()->for($tenant)->create();
    $purchase = $tenant->purchases()->create(['supplier_id' => $supplier->id, 'invoice_date' => '2026-07-13']);

    $show = $this->actingAs($user)->get(route('purchases.show', $purchase))->assertOk()->getContent();
    expect($show)->toContain('Perc. IIBB Bs.As. — 1,5% · Neto gravado')
        ->and($show)->toContain('perceptionConceptManager')
        ->and($show)->toContain('Administrar percepciones');

    $this->actingAs($user)->get(route('purchases.index'))->assertOk()
        ->assertSee('perceptionConceptManager', false);
});

<?php

use App\Enums\TenantUserRole;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Models\Tenant;
use Carbon\CarbonImmutable;

// "Hoy": miércoles 18/11/2026 → la semana actual empieza el lunes 16/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
});

// --- Planillas (alta, edición, pausa, borrado) ---

test('owner crea una planilla y queda al final del orden', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    OrderSheet::factory()->for($tenant)->create(['name' => 'Secos', 'position' => 3]);

    $this->actingAs($user)
        ->post(route('pedidos.planillas.store'), ['name' => 'Tortas'])
        ->assertRedirect();

    $sheet = $tenant->orderSheets()->where('name', 'Tortas')->sole();
    expect($sheet->position)->toBe(4)
        ->and($sheet->active)->toBeTrue();
});

test('el nombre de la planilla es único por negocio pero se puede repetir en otro', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    OrderSheet::factory()->for(Tenant::factory()->create())->create(['name' => 'Reparto']);

    $this->actingAs($user)->post(route('pedidos.planillas.store'), ['name' => 'Diarios'])->assertSessionHasErrors('name');
    $this->actingAs($user)->post(route('pedidos.planillas.store'), ['name' => 'Reparto'])->assertSessionHasNoErrors();
});

test('owner renombra una planilla', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Viejo']);

    $this->actingAs($user)
        ->put(route('pedidos.planillas.update', $sheet), ['name' => 'Nuevo'])
        ->assertSessionHasNoErrors();

    expect($sheet->fresh()->name)->toBe('Nuevo');
});

test('una planilla pausada deja de aparecer en las pestañas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Tortas']);
    OrderSheet::factory()->for($tenant)->create(['name' => 'Secos', 'position' => 1]);

    $this->actingAs($user)->patch(route('pedidos.planillas.toggle-active', $sheet))->assertRedirect();

    expect($sheet->fresh()->active)->toBeFalse();
    // El modal de configuración lista todas, así que se mira el link de la pestaña.
    $this->actingAs($user)->get(route('pedidos.index'))->assertOk()
        ->assertSee('Secos')
        ->assertDontSee('?sheet='.$sheet->id.'&amp;', false);
});

test('se borra una planilla sin datos pero no una con semanas cargadas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $vacia = OrderSheet::factory()->for($tenant)->create();
    $conDatos = OrderSheet::factory()->for($tenant)->create();
    $week = OrderSheetWeek::factory()->for($conDatos)->create();
    OrderSheetLine::factory()->for($week, 'week')->create();

    $this->actingAs($user)->delete(route('pedidos.planillas.destroy', $vacia))->assertRedirect();
    $this->actingAs($user)->delete(route('pedidos.planillas.destroy', $conDatos))->assertSessionHasErrors('order_sheet');

    expect(OrderSheet::find($vacia->id))->toBeNull()
        ->and(OrderSheet::find($conDatos->id))->not->toBeNull();
});

test('una planilla de otro negocio resuelve 404', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $ajena = OrderSheet::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($user)->put(route('pedidos.planillas.update', $ajena), ['name' => 'X'])->assertNotFound();
    $this->actingAs($user)->put(route('pedidos.semana.update', $ajena), ['start' => '2026-11-16', 'lines' => []])->assertNotFound();
});

// --- Permisos ---

test('un viewer ve las planillas pero no puede editarlas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Secos']);

    $this->actingAs($user)->get(route('pedidos.index'))->assertOk()
        ->assertSee('Secos')
        ->assertDontSee('Guardar semana')
        ->assertDontSee('Configurar planillas');

    $this->actingAs($user)->post(route('pedidos.planillas.store'), ['name' => 'Nueva'])->assertForbidden();
    $this->actingAs($user)->put(route('pedidos.semana.update', $sheet), ['start' => '2026-11-16', 'lines' => []])->assertForbidden();
});

// --- Pantalla ---

test('sin planillas la pantalla invita a crear la primera', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    // El botón abre el modal con @click: sin un x-data por encima Alpine lo ignora.
    $this->actingAs($user)->get(route('pedidos.index'))->assertOk()
        ->assertSee('Crear la primera planilla')
        ->assertSee('lg:px-8" x-data>', false)
        ->assertSee("open-modal', 'order-sheets-manage'", false);
});

test('la pantalla muestra los artículos de la semana y copia la anterior en una futura', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create(['name' => 'Diarios']);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $product = Product::factory()->for($tenant)->create(['name' => 'Pan francés']);
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([20])->create(['product_id' => $product->id]);

    $this->actingAs($user)->get(route('pedidos.index', ['sheet' => $sheet->id]))->assertOk()
        ->assertSee('Semana del 16/11/2026 al 22/11/2026')
        ->assertSee('Pan francés');

    $this->actingAs($user)->get(route('pedidos.index', ['sheet' => $sheet->id, 'start' => '2026-12-02']))->assertOk()
        ->assertSee('Semana del 30/11/2026 al 06/12/2026')
        ->assertSee('Pan francés');

    expect($sheet->weeks()->count())->toBe(2);
});

test('mirar una semana pasada sin datos no crea nada', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();

    $this->actingAs($user)->get(route('pedidos.index', ['sheet' => $sheet->id, 'start' => '2026-10-05']))->assertOk();

    expect($sheet->weeks()->count())->toBe(0);
});

// --- Guardar la semana ---

test('owner guarda la semana y el cambio queda solo en esa semana', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = Product::factory()->for($tenant)->create();
    $factura = Product::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('pedidos.semana.update', $sheet), [
            'start' => '2026-11-18',
            'lines' => [
                ['product_id' => $pan->id, 'quantities' => [10, 20, '', 0, 5, 6, 7]],
                ['product_id' => $factura->id, 'quantities' => [1, 1, 1, 1, 1, 1, 1]],
            ],
        ])
        ->assertRedirect(route('pedidos.index', ['sheet' => $sheet->id, 'start' => '2026-11-16']));

    $week = $sheet->weeks()->whereDate('starts_on', '2026-11-16')->sole();
    expect($week->lines)->toHaveCount(2)
        ->and($week->lines[0]->product_id)->toBe($pan->id)
        ->and($week->lines[0]->quantities())->toBe([10.0, 20.0, 0.0, 0.0, 5.0, 6.0, 7.0]);

    // Guardar de nuevo con una fila menos reemplaza el contenido.
    $this->actingAs($user)->put(route('pedidos.semana.update', $sheet), [
        'start' => '2026-11-16',
        'lines' => [['product_id' => $pan->id, 'quantities' => [1, 2, 3, 4, 5, 6, 7]]],
    ])->assertSessionHasNoErrors();

    expect($week->fresh()->lines)->toHaveCount(1);
});

test('guardar rechaza artículos ajenos, repetidos y cantidades negativas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $propio = Product::factory()->for($tenant)->create();
    $ajeno = Product::factory()->for(Tenant::factory()->create())->create();
    $ok = [1, 1, 1, 1, 1, 1, 1];

    $url = route('pedidos.semana.update', $sheet);

    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [['product_id' => $ajeno->id, 'quantities' => $ok]]])
        ->assertSessionHasErrors('lines.0.product_id');
    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [
        ['product_id' => $propio->id, 'quantities' => $ok],
        ['product_id' => $propio->id, 'quantities' => $ok],
    ]])->assertSessionHasErrors('lines.0.product_id');
    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [['product_id' => $propio->id, 'quantities' => [-1, 1, 1, 1, 1, 1, 1]]]])
        ->assertSessionHasErrors('lines.0.quantities.0');
    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [['product_id' => $propio->id, 'quantities' => [1, 2]]]])
        ->assertSessionHasErrors('lines.0.quantities');

    expect($sheet->weeks()->count())->toBe(0);
});

test('guardar una semana vacía deja la planilla sin filas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();

    $this->actingAs($user)->put(route('pedidos.semana.update', $sheet), ['start' => '2026-11-16', 'lines' => []])
        ->assertSessionHasNoErrors();

    expect($sheet->weeks()->sole()->lines)->toHaveCount(0);
});

test('volver a copiar la semana anterior restituye lo de la semana previa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $prev = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-09')->create();
    $line = OrderSheetLine::factory()->for($prev, 'week')->withQuantities([8, 8, 8, 8, 8, 8, 8])->create();
    $current = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();

    $this->actingAs($user)
        ->post(route('pedidos.semana.recopy', $sheet), ['start' => '2026-11-16'])
        ->assertRedirect(route('pedidos.index', ['sheet' => $sheet->id, 'start' => '2026-11-16']));

    $lines = $current->fresh()->lines;
    expect($lines)->toHaveCount(1)
        ->and($lines->first()->product_id)->toBe($line->product_id)
        ->and($lines->first()->day_7)->toBe(8.0);
});

// --- Navegación ---

test('el dashboard y el menú apuntan a Pedidos', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(route('pedidos.index'), false);
    $this->actingAs($user)->get(route('pedidos.index'))->assertOk()->assertSee('sidebar-pedidos', false);
});

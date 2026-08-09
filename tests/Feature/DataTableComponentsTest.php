<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// Anclas de x-sortable-th y x-data-table. Los listados renderizan la fila una
// sola vez y el CSS decide si se ve como fila de tabla o como card; el test que
// vivía acá antes afirmaba lo contrario (assertSeeInOrder del nombre dos veces),
// así que hoy fija el invariante inverso.
//
// Al tocar estas anclas, verificar que fallen contra un revert a
// x-responsive-table: un assertSee del nombre pasa igual con y sin el arreglo
// porque el payload de openEdit lo repite en JSON.

function ownerForDataTable(): array
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

test('el encabezado ordenable alterna la dirección y preserva los filtros', function () {
    [$user, $tenant] = ownerForDataTable();
    LaborType::factory()->for($tenant)->create(['name' => 'Panadero']);

    $html = $this->actingAs($user)
        ->get(route('labor-types.index', ['search' => 'Pan', 'sort' => 'name', 'dir' => 'asc']))
        ->assertOk()
        ->getContent();

    // La columna activa ofrece invertir la dirección sin perder la búsqueda.
    expect($html)->toContain('sort=name')
        ->and($html)->toContain('dir=desc')
        ->and($html)->toContain('search=Pan')
        ->and($html)->toContain('↑');
});

test('cada registro se renderiza en una sola fila, no en card + fila', function () {
    [$user, $tenant] = ownerForDataTable();
    LaborType::factory()->for($tenant)->create(['name' => 'Pastelero']);
    LaborType::factory()->for($tenant)->create(['name' => 'Panadero']);

    $html = $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertOk()
        ->getContent();

    // Dos registros, dos celdas de título: si volvieran las cards serían cuatro
    // bloques y el nombre aparecería dos veces como texto.
    expect(substr_count($html, 'data-role="title"'))->toBe(2)
        ->and(preg_match_all('/>\s*Pastelero\s*</', $html))->toBe(1)
        ->and(preg_match_all('/>\s*Panadero\s*</', $html))->toBe(1)
        // El envoltorio de doble render de x-responsive-table ya no está.
        ->and($html)->not->toContain("mobileExpanded ? 'hidden' : 'md:hidden'");
});

test('el toggle entre card y tabla sigue disponible sobre el mismo marcado', function () {
    [$user, $tenant] = ownerForDataTable();
    LaborType::factory()->for($tenant)->create(['name' => 'Pastelero']);

    $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertOk()
        ->assertSee('Ver tabla completa')
        ->assertSee('Volver a cards')
        // El modo lo decide el CSS a partir de este atributo, no un segundo marcado.
        ->assertSee(":data-view=\"mobileExpanded ? 'table' : 'cards'\"", false);
});

test('la cantidad de celdas de una fila coincide con la de encabezados', function () {
    [$user, $tenant] = ownerForDataTable();
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => Unit::Kilogramo]);

    $html = $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->getContent();

    preg_match('/<thead.*?<tr>(.*?)<\/tr>/s', $html, $head);
    preg_match('/<tbody>\s*<tr[^>]*>(.*?)<\/tr>/s', $html, $firstRow);

    expect(substr_count($head[1], '<th'))
        ->toBe(substr_count($firstRow[1], 'data-role='))
        ->toBeGreaterThan(0);
});

test('una columna de baja prioridad se oculta en la card sin salir de la tabla', function () {
    [$user, $tenant] = ownerForDataTable();
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'cost_per_package' => 5000]);

    $html = $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->getContent();

    // «Por envase» sigue en el <thead> y su celda se marca para el modo card.
    expect($html)->toContain('Por envase')
        ->and(substr_count($html, 'data-cards="hide"'))->toBe(1);
});

test('la edición inline de stock vive en la fila y no se duplica', function () {
    [$user, $tenant] = ownerForDataTable();
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000']);

    $html = $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->getContent();

    // Antes el editor existía sólo en la copia de tabla y la card mostraba el
    // stock de sólo lectura; ahora hay un único editor por ingrediente.
    expect(substr_count($html, route('stock.level.update', ['ingredient', $ingredient->id])))->toBe(1)
        ->and($html)->toContain('data-label="Stock:"');
});

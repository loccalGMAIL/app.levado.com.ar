<?php

use App\Enums\TenantUserRole;
use App\Models\LaborType;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// Anclas de los componentes x-sortable-th y x-responsive-table que comparten
// las vistas de índice (migrados en el 2º lote del mediano plazo).

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

test('las vistas migradas renderizan cards y tabla con el toggle', function () {
    [$user, $tenant] = ownerForDataTable();
    LaborType::factory()->for($tenant)->create(['name' => 'Pastelero']);

    $this->actingAs($user)
        ->get(route('labor-types.index'))
        ->assertOk()
        ->assertSee('Ver tabla completa')
        ->assertSee('Volver a cards')
        ->assertSeeInOrder(['Pastelero', 'Pastelero']); // card + fila de tabla
});

<?php

use App\Enums\MobileShortcut;
use App\Enums\TenantUserRole;

function barLabel(string $label): string
{
    return '<span class="text-[10px] font-semibold leading-none">'.$label.'</span>';
}

test('el owner ve la pantalla y guarda sus accesos rápidos', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->get(route('mobile-shortcuts.edit'))->assertOk()->assertSee('Accesos rápidos');

    $this->actingAs($user)->patch(route('mobile-shortcuts.update'), [
        'shortcuts' => ['stock', 'products', 'reparto'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($tenant->fresh()->getSetting('mobile_nav.shortcuts'))->toBe('stock,products,reparto')
        ->and($tenant->fresh()->mobileShortcuts())->toBe([MobileShortcut::Stock, MobileShortcut::Products, MobileShortcut::Reparto]);
});

test('admin y viewer no pueden acceder a la configuración', function (TenantUserRole $role) {
    [$user] = tenantUserAs($role);

    $this->actingAs($user)->get(route('mobile-shortcuts.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('mobile-shortcuts.update'), [
        'shortcuts' => ['stock', 'products', 'purchases'],
    ])->assertForbidden();
})->with([TenantUserRole::Admin, TenantUserRole::Viewer]);

test('valida cantidad, duplicados y valores inexistentes', function (array $shortcuts) {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->patch(route('mobile-shortcuts.update'), ['shortcuts' => $shortcuts])
        ->assertSessionHasErrors();

    expect($tenant->fresh()->getSetting('mobile_nav.shortcuts'))->toBeNull();
})->with([
    'dos accesos' => [['stock', 'products']],
    'cuatro accesos' => [['stock', 'products', 'purchases', 'recipes']],
    'duplicados' => [['stock', 'stock', 'products']],
    'inexistente' => [['stock', 'products', 'foo']],
]);

test('sin configurar la barra muestra Recetas, Ingredientes y Compras', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee(barLabel('Recetas'), false)
        ->assertSee(barLabel('Ingredientes'), false)
        ->assertSee(barLabel('Compras'), false)
        ->assertDontSee(barLabel('Stock'), false);
});

test('la barra refleja los accesos del tenant y el resto pasa al drawer', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->setSetting('mobile_nav.shortcuts', 'stock,products,reparto');

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain(barLabel('Stock'))
        ->toContain(barLabel('Artículos'))
        ->toContain(barLabel('Reparto'))
        ->not->toContain(barLabel('Compras'))
        // Compras ya no está en la barra pero sigue accesible desde "Más"
        ->toContain('href="'.route('purchases.index').'" @click="open = false"');
});

test('un acceso que el rol no puede ver no aparece en la barra', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    $tenant->setSetting('mobile_nav.shortcuts', 'stock,products,reparto');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee(barLabel('Stock'), false)
        ->assertDontSee(route('reparto.clientes.index'), false);
});

test('un setting corrupto vuelve a los accesos por defecto', function (string $raw) {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $tenant->setSetting('mobile_nav.shortcuts', $raw);

    expect($tenant->fresh()->mobileShortcuts())->toBe(MobileShortcut::defaults());
})->with(['valor inválido' => 'foo,recipes,stock', 'pocos' => 'recipes,stock', 'repetidos' => 'recipes,recipes,stock', 'vacío' => '']);

test('la configuración de un tenant no afecta a otro', function () {
    [, $tenantA] = tenantUserAs(TenantUserRole::Owner);
    [$userB] = tenantUserAs(TenantUserRole::Owner);
    $tenantA->setSetting('mobile_nav.shortcuts', 'stock,products,suppliers');

    $this->actingAs($userB)->get(route('dashboard'))
        ->assertSee(barLabel('Recetas'), false)
        ->assertDontSee(barLabel('Stock'), false);
});

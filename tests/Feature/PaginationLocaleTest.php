<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;

test('la paginación se muestra en castellano', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->count(25)->create();

    $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->assertSee('Mostrando')
        ->assertSee('resultados')
        ->assertDontSee('Showing')
        ->assertDontSee('results');
});

<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// El objeto Alpine de las filas vivía adentro del @foreach, así que el mismo
// JavaScript se serializaba una vez por fila: 68 líneas × 20 recetas en el
// dashboard, 47 por celda en la matriz (recetas × listas), 37 por fila en
// ingredientes. Ahora se define una vez en un módulo Vite.
//
// Estas anclas fijan que el CUERPO del objeto no esté en el HTML y que la
// llamada al factory aparezca exactamente una vez por fila. Fallan contra un
// revert: al volver el objeto inline, `async savePrice` reaparece N veces.

function ownerForAlpineRows(): array
{
    $tenant = Tenant::factory()->create(['productive_hours_month' => 160]);
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('el dashboard llama al factory una vez por receta y no manda el cuerpo del objeto', function () {
    [$user, $tenant] = ownerForAlpineRows();
    foreach (range(1, 5) as $i) {
        Recipe::factory()->for($tenant)->create(['name' => "Receta {$i}", 'active' => true]);
    }

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count($html, 'x-data="priceRow('))->toBe(5)
        ->and($html)->not->toContain('async savePrice')
        ->and($html)->not->toContain('get badgeLabel');
});

test('la matriz llama al factory una vez por celda editable y no manda el cuerpo', function () {
    [$user, $tenant] = ownerForAlpineRows();
    $recipes = collect(range(1, 3))->map(
        fn ($i) => Recipe::factory()->for($tenant)->create(['name' => "Receta {$i}", 'active' => true])
    );
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'active' => true]);

    $html = $this->actingAs($user)->get(route('price-lists.matrix'))->assertOk()->getContent();

    // 3 recetas × 2 listas (la default del tenant + la mayorista) = 6 celdas.
    $lists = $tenant->priceLists()->where('active', true)->count();

    expect(substr_count($html, 'x-data="priceCell('))->toBe($recipes->count() * $lists)
        ->and($html)->not->toContain('async savePrice');
});

test('ingredientes llama al factory una vez por fila y no manda el cuerpo', function () {
    [$user, $tenant] = ownerForAlpineRows();
    foreach (range(1, 4) as $i) {
        Ingredient::factory()->for($tenant)->create(['name' => "Ingrediente {$i}", 'unit' => Unit::Kilogramo]);
    }

    $html = $this->actingAs($user)->get(route('ingredients.index'))->assertOk()->getContent();

    // `counted_quantity` es la clave que el editor manda al endpoint: estaba en
    // el cuerpo inline y ahora vive sólo en el módulo.
    expect(substr_count($html, 'x-data="stockCell('))->toBe(4)
        ->and($html)->not->toContain('counted_quantity');
});

test('envases emite un solo costCell y un solo stockCell por fila', function () {
    [$user, $tenant] = ownerForAlpineRows();
    foreach (range(1, 3) as $i) {
        Packaging::factory()->for($tenant)->create(['name' => "Envase {$i}", 'cost_per_unit' => 100]);
    }

    $html = $this->actingAs($user)->get(route('packaging.index'))->assertOk()->getContent();

    // Antes eran TRES objetos por envase: el costo en la card, el costo en la
    // fila (copia literal con otro x-ref) y el stock.
    expect(substr_count($html, 'x-data="costCell('))->toBe(3)
        ->and(substr_count($html, 'x-data="stockCell('))->toBe(3)
        ->and($html)->not->toContain('counted_quantity')
        ->and($html)->not->toContain('async saveCost');
});

test('recetas emite un solo editor de precio por fila', function () {
    [$user, $tenant] = ownerForAlpineRows();
    foreach (range(1, 3) as $i) {
        Recipe::factory()->for($tenant)->create(['name' => "Receta {$i}", 'active' => true]);
    }

    $html = $this->actingAs($user)->get(route('recipes.index'))->assertOk()->getContent();

    expect(substr_count($html, 'x-data="priceCell('))->toBe(3)
        ->and($html)->not->toContain('async savePrice')
        ->and($html)->not->toContain('priceInputCard');
});

test('el tramo del margen viaja al cliente y el color ya no se calcula en la vista', function () {
    [$user, $tenant] = ownerForAlpineRows();
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan lactal', 'active' => true]);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $tenant->defaultPriceList()->id,
        'recipe_id' => $recipe->id,
        'price' => 700,
    ]);

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // El HTML manda el tramo, no las clases: los cortes son del enum y el
    // cliente sólo los traduce. Js::from escapa las comillas como \u0022.
    $decoded = str_replace('\u0022', '"', $html);

    expect($decoded)->toContain('"tier":')
        ->and($html)->not->toContain('marginPct >= 60')
        ->and($html)->not->toContain("pct >= 40 ? 'text-amber-600'");
});

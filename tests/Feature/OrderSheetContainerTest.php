<?php

use App\Enums\ContainerType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\OrderSheetWeekService;
use Carbon\CarbonImmutable;

// "Hoy": miércoles 18/11/2026 → la semana actual empieza el lunes 16/11.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00:00'));
});

/** Elaborado del negocio cuya receta se pide en latas de $per unidades. */
function productInTins($tenant, float $per = 30, string $name = 'Pan francés'): Product
{
    $recipe = Recipe::factory()->for($tenant)->inContainers(ContainerType::Tin, $per)->create(['yield_unit' => Unit::Unidad->value]);

    return Product::factory()->for($tenant)->manufactured()->create(['name' => $name, 'recipe_id' => $recipe->id, 'unit' => Unit::Unidad->value]);
}

/** Datos de edición de una receta con lo que hay que mandar siempre. */
function recipeUpdatePayload(Recipe $recipe, array $overrides = []): array
{
    return array_merge([
        'name' => $recipe->name,
        'yield_quantity' => (float) $recipe->yield_quantity,
        'yield_unit' => $recipe->yield_unit->value,
        'is_semi_elaborate' => 0,
    ], $overrides);
}

// --- Receta: bandeja o lata y cuánto entra ---

test('owner indica en la receta que se pide en latas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), recipeUpdatePayload($recipe, ['container_type' => 'lata', 'units_per_container' => '30']))
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->container_type)->toBe(ContainerType::Tin)
        ->and($recipe->fresh()->units_per_container)->toBe(30.0)
        ->and($recipe->fresh()->usesContainer())->toBeTrue();
});

test('elegir bandeja o lata en la receta exige cuánto entra en una', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), recipeUpdatePayload($recipe, ['container_type' => 'bandeja']))
        ->assertSessionHasErrors('units_per_container');
});

test('volver a unidades en la receta borra el rinde', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->inContainers(ContainerType::Tin, 30)->create();

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), recipeUpdatePayload($recipe, ['container_type' => '', 'units_per_container' => '30']))
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->container_type)->toBeNull()
        ->and($recipe->fresh()->units_per_container)->toBeNull();
});

test('el artículo hereda el recipiente de su receta y un pack lo divide', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->inContainers(ContainerType::Tray, 24)->create(['yield_unit' => Unit::Unidad->value]);
    $base = Product::factory()->for($tenant)->manufactured()->create(['recipe_id' => $recipe->id]);
    $pack = Product::factory()->presentationOf($recipe, 6)->create();
    $reventa = Product::factory()->for($tenant)->resale()->create();

    expect($base->load('recipe')->containerPayload())->toBe(['type' => 'bandeja', 'label' => 'Bandeja', 'short' => 'band.', 'per' => 24.0])
        ->and($pack->load('recipe')->unitsPerContainer())->toBe(4.0)
        ->and($reventa->load('recipe')->containerPayload())->toBeNull();
});

// --- Planilla: se carga en bandejas/latas ---

test('guardar una fila de un artículo con lata guarda latas y la medida', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant);
    $torta = Product::factory()->for($tenant)->manufactured()->create();

    $this->actingAs($user)->put(route('pedidos.semana.update', $sheet), [
        'start' => '2026-11-16',
        'lines' => [
            ['product_id' => $pan->id, 'quantities' => [1.5, 2, 0, 0, 0, 0, 1.25]],
            ['product_id' => $torta->id, 'quantities' => [3, 0, 0, 0, 0, 0, 0]],
        ],
    ])->assertSessionHasNoErrors();

    $lines = $sheet->weeks()->sole()->lines;
    expect($lines[0]->container_type)->toBe(ContainerType::Tin)
        ->and($lines[0]->day_1)->toBe(1.5)
        ->and($lines[1]->container_type)->toBeNull()
        ->and($lines[1]->day_1)->toBe(3.0);
});

test('las latas van de a cuartos; las unidades no', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant);
    $torta = Product::factory()->for($tenant)->manufactured()->create();
    $url = route('pedidos.semana.update', $sheet);

    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [
        ['product_id' => $pan->id, 'quantities' => [1.3, 0, 0, 0, 0, 0, 0]],
    ]])->assertSessionHasErrors('lines.0.quantities.0');

    $this->actingAs($user)->put($url, ['start' => '2026-11-16', 'lines' => [
        ['product_id' => $torta->id, 'quantities' => [1.3, 0, 0, 0, 0, 0, 0]],
        ['product_id' => $pan->id, 'quantities' => [1.75, 0, 0, 0, 0, 0, 0]],
    ]])->assertSessionHasNoErrors();
});

test('la copia de la semana conserva las latas', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([2])->create(['product_id' => $pan->id, 'container_type' => ContainerType::Tin]);

    $next = app(OrderSheetWeekService::class)->weekFor($sheet, CarbonImmutable::parse('2026-11-23'));

    expect($next->lines->first()->container_type)->toBe(ContainerType::Tin)
        ->and($next->lines->first()->day_1)->toBe(2.0);
});

test('la pantalla muestra la lata, su rinde y las latas cargadas', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant, 30);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    OrderSheetLine::factory()->for($week, 'week')->withQuantities([1.5])->create(['product_id' => $pan->id, 'container_type' => ContainerType::Tin]);

    $response = $this->actingAs($user)->get(route('pedidos.index', ['sheet' => $sheet->id]))->assertOk();

    $grid = $response->viewData('lines');
    expect($grid[0]['container'])->toBe(['type' => 'lata', 'label' => 'Lata', 'short' => 'latas', 'per' => 30.0])
        ->and($grid[0]['q'][0])->toBe(1.5)
        ->and($grid[0]['warning'])->toBeNull();
});

// --- Cambios de rinde ---

test('cambiar el rinde respeta las latas y recalcula las unidades', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant, 30);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $line = OrderSheetLine::factory()->for($week, 'week')->withQuantities([1.5])->create(['product_id' => $pan->id, 'container_type' => ContainerType::Tin]);

    $pan->recipe->update(['units_per_container' => 20]);

    $row = app(OrderSheetWeekService::class)->presentLine($line->fresh()->load('product.recipe'));
    expect($row['q'][0])->toBe(1.5)
        ->and($row['container']['per'])->toBe(20.0);
});

test('si la receta gana rinde, lo cargado en unidades se muestra en latas', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = Product::factory()->for($tenant)->manufactured()->create();
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $line = OrderSheetLine::factory()->for($week, 'week')->withQuantities([45, 60])->create(['product_id' => $pan->id]);

    $pan->recipe->update(['container_type' => ContainerType::Tin, 'units_per_container' => 30]);

    $row = app(OrderSheetWeekService::class)->presentLine($line->fresh()->load('product.recipe'));
    expect($row['q'][0])->toBe(1.5)
        ->and($row['q'][1])->toBe(2.0);
});

test('si la receta pierde el rinde, avisa en vez de convertir', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $sheet = OrderSheet::factory()->for($tenant)->create();
    $pan = productInTins($tenant);
    $week = OrderSheetWeek::factory()->for($sheet)->startingOn('2026-11-16')->create();
    $line = OrderSheetLine::factory()->for($week, 'week')->withQuantities([2])->create(['product_id' => $pan->id, 'container_type' => ContainerType::Tin]);

    $pan->recipe->update(['container_type' => null, 'units_per_container' => null]);

    $row = app(OrderSheetWeekService::class)->presentLine($line->fresh()->load('product.recipe'));
    expect($row['container'])->toBeNull()
        ->and($row['q'][0])->toBe(2.0)
        ->and($row['warning'])->toContain('latas');
});

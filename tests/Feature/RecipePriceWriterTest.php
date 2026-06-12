<?php

use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\RecipePriceWriter;

test('set crea el precio y registra un log inicial', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();
    $list = PriceList::factory()->for($tenant)->create();

    $recipePrice = app(RecipePriceWriter::class)->set($recipe, $list, 1500.50);

    expect($recipePrice)->not->toBeNull()
        ->and((float) $recipePrice->price)->toBe(1500.50)
        ->and($recipePrice->tenant_id)->toBe($tenant->id);

    expect($recipe->priceLogs()->count())->toBe(1)
        ->and((float) $recipe->priceLogs()->first()->price)->toBe(1500.50);
});

test('set actualiza el precio existente y loguea solo si cambia el monto', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();
    $list = PriceList::factory()->for($tenant)->create();
    $writer = app(RecipePriceWriter::class);

    $writer->set($recipe, $list, 1000.0);
    $writer->set($recipe, $list, 1000.0);

    expect(RecipePrice::count())->toBe(1)
        ->and($recipe->priceLogs()->count())->toBe(1);

    $writer->set($recipe, $list, 1200.0);

    expect(RecipePrice::count())->toBe(1)
        ->and((float) RecipePrice::first()->price)->toBe(1200.0)
        ->and($recipe->priceLogs()->count())->toBe(2);
});

test('set con null elimina el precio sin loguear', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();
    $list = PriceList::factory()->for($tenant)->create();
    $writer = app(RecipePriceWriter::class);

    $writer->set($recipe, $list, 800.0);
    $result = $writer->set($recipe, $list, null);

    expect($result)->toBeNull()
        ->and(RecipePrice::count())->toBe(0)
        ->and($recipe->priceLogs()->count())->toBe(1);
});

test('defaultPriceList crea la lista General una sola vez', function () {
    $tenant = Tenant::factory()->create();

    $first = $tenant->defaultPriceList();
    $second = $tenant->defaultPriceList();

    expect($first->id)->toBe($second->id)
        ->and($first->name)->toBe('General')
        ->and($first->is_default)->toBeTrue()
        ->and($first->active)->toBeTrue()
        ->and($tenant->priceLists()->count())->toBe(1);
});

test('aislamiento: defaultPriceList de un tenant no toca la de otro', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $listA = $tenantA->defaultPriceList();
    $listB = $tenantB->defaultPriceList();

    expect($listA->id)->not->toBe($listB->id)
        ->and($listA->tenant_id)->toBe($tenantA->id)
        ->and($listB->tenant_id)->toBe($tenantB->id);
});

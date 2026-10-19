<?php

use App\Enums\LineMeasure;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Recipe;

// productionSetup() es global (ProductionControllerTest): elaborado de una
// receta que rinde 12 u.

test('sin nombre de unidad de producción la unidad es el rendimiento completo y se llama Lata', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;

    expect($recipe->hasCustomProductionUnit())->toBeFalse()
        ->and($recipe->productionUnitSize())->toBe(12.0)
        ->and($recipe->productionUnitLabel())->toBe('Lata');
});

test('una unidad de producción definida manda sobre el rendimiento', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;
    $recipe->update(['production_unit_name' => 'lata', 'production_unit_quantity' => 6]);

    expect($recipe->hasCustomProductionUnit())->toBeTrue()
        ->and($recipe->productionUnitSize())->toBe(6.0)
        ->and($recipe->productionUnitLabel())->toBe('lata');
});

test('baseQuantityFor convierte unidades de producción a unidades del artículo', function () {
    [$user, $tenant, $product] = productionSetup();
    $product->recipe->update(['production_unit_name' => 'lata', 'production_unit_quantity' => 6]);
    $product = $product->fresh('recipe');

    expect($product->baseQuantityFor(1.5, LineMeasure::ProductionUnit))->toBe(9.0)
        ->and($product->baseQuantityFor(7, LineMeasure::Unit))->toBe(7.0);
});

test('baseQuantityFor convierte entre unidades compatibles', function () {
    [$user, $tenant, $product] = productionSetup();
    // Receta que rinde 42 kg con una unidad de 21 kg; el artículo se produce en gramos.
    $product->recipe->update([
        'yield_quantity' => 42, 'yield_unit' => Unit::Kilogramo->value,
        'production_unit_name' => 'tanda chica', 'production_unit_quantity' => 21,
    ]);
    $product->update(['unit' => Unit::Gramo->value]);

    expect($product->fresh('recipe')->baseQuantityFor(2, LineMeasure::ProductionUnit))->toBe(42000.0);
});

test('baseQuantityFor devuelve null si la unidad del artículo no es compatible con el rendimiento', function () {
    [$user, $tenant, $product] = productionSetup();
    $product->recipe->update(['yield_unit' => Unit::Kilogramo->value]);

    expect($product->fresh('recipe')->baseQuantityFor(1, LineMeasure::ProductionUnit))->toBeNull();
});

test('el modal de producción guarda el nombre y la equivalencia', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;

    $this->actingAs($user)
        ->patch(route('recipes.production.update', $recipe), [
            'production_unit_name' => 'Lata',
            'production_unit_quantity' => 3.5,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($recipe->fresh()->production_unit_name)->toBe('Lata')
        ->and($recipe->fresh()->productionUnitSize())->toBe(3.5);
});

test('vaciar el modal de producción vuelve al default', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;
    $recipe->update(['production_unit_name' => 'Bandeja', 'production_unit_quantity' => 4]);

    $this->actingAs($user)
        ->patch(route('recipes.production.update', $recipe), ['production_unit_name' => '', 'production_unit_quantity' => ''])
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->production_unit_name)->toBeNull()
        ->and($recipe->fresh()->production_unit_quantity)->toBeNull()
        ->and($recipe->fresh()->productionUnitLabel())->toBe('Lata')
        ->and($recipe->fresh()->productionUnitSize())->toBe(12.0);
});

test('el nombre y la equivalencia van juntos y la equivalencia es positiva', function () {
    [$user, $tenant, $product] = productionSetup();
    $url = route('recipes.production.update', $product->recipe);

    $this->actingAs($user)->patch($url, ['production_unit_name' => 'Lata'])
        ->assertSessionHasErrors('production_unit_quantity');
    $this->actingAs($user)->patch($url, ['production_unit_quantity' => 6])
        ->assertSessionHasErrors('production_unit_name');
    $this->actingAs($user)->patch($url, ['production_unit_name' => 'Lata', 'production_unit_quantity' => 0])
        ->assertSessionHasErrors('production_unit_quantity');
});

test('un viewer no puede cambiar la producción de la receta', function () {
    [$user, $tenant, $product] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->patch(route('recipes.production.update', $product->recipe), ['production_unit_name' => 'Lata', 'production_unit_quantity' => 6])
        ->assertForbidden();
});

test('no se puede cambiar la producción de la receta de otro negocio', function () {
    [$user] = productionSetup();
    [, , $otherProduct] = productionSetup();

    $this->actingAs($user)
        ->patch(route('recipes.production.update', $otherProduct->recipe), ['production_unit_name' => 'Lata', 'production_unit_quantity' => 6])
        ->assertNotFound();
});

test('el formulario principal de la receta no toca la unidad de producción', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;
    $recipe->update(['production_unit_name' => 'Lata', 'production_unit_quantity' => 6]);

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'name' => 'Otro nombre',
            'yield_quantity' => 12,
            'yield_unit' => 'u',
            'production_unit_name' => 'Intruso',
        ])
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->production_unit_name)->toBe('Lata')
        ->and($recipe->fresh()->name)->toBe('Otro nombre');
});

test('el modal de edición ofrece el botón Producción junto a Guardar y Cancelar', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->get(route('recipes.show', $product->recipe))
        ->assertOk()
        ->assertSeeInOrder(['Guardar cambios', "open-modal', 'recipe-production'", 'Producción', "close-modal', 'recipe-edit-info'", 'Cancelar'], false)
        ->assertSee('recipes/'.$product->recipe->id.'/production', false);
});

test('la equivalencia del modal de producción arranca con el rinde de la receta', function () {
    [$user, $tenant, $product] = productionSetup();

    // La receta rinde 12 u y no tiene unidad de producción cargada.
    $this->actingAs($user)
        ->get(route('recipes.show', $product->recipe))
        ->assertSeeInOrder(['id="production_unit_quantity"', 'value="12"'], false)
        ->assertSeeInOrder(['id="production_unit_name"', 'value="Lata"'], false);

    $product->recipe->update(['production_unit_name' => 'Bandeja', 'production_unit_quantity' => 3]);

    $this->actingAs($user)
        ->get(route('recipes.show', $product->recipe))
        ->assertSeeInOrder(['id="production_unit_quantity"', 'value="3"'], false)
        ->assertSeeInOrder(['id="production_unit_name"', 'value="Bandeja"'], false);
});

test('copiar una receta conserva su unidad de producción', function () {
    [$user, $tenant, $product] = productionSetup();
    $product->recipe->update(['production_unit_name' => 'lata', 'production_unit_quantity' => 6]);

    $this->actingAs($user)->post(route('recipes.copy', $product->recipe))->assertRedirect();

    $copy = Recipe::where('name', 'like', '% (copia)')->first();
    expect($copy->production_unit_name)->toBe('lata');
});

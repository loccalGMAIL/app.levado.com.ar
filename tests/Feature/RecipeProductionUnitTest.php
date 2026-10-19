<?php

use App\Enums\LineMeasure;
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

test('el formulario de receta guarda la unidad de producción', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'name' => $recipe->name,
            'yield_quantity' => 12,
            'yield_unit' => 'u',
            'production_unit_name' => 'Lata grande',
        ])
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->production_unit_name)->toBe('Lata grande')
        ->and($recipe->fresh()->productionUnitSize())->toBe(12.0);
});

test('dejar vacío el nombre de la unidad de producción vuelve al default', function () {
    [$user, $tenant, $product] = productionSetup();
    $recipe = $product->recipe;
    $recipe->update(['production_unit_name' => 'Bandeja']);

    $this->actingAs($user)
        ->put(route('recipes.update', $recipe), [
            'name' => $recipe->name,
            'yield_quantity' => 12,
            'yield_unit' => 'u',
            'production_unit_name' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($recipe->fresh()->production_unit_name)->toBeNull()
        ->and($recipe->fresh()->productionUnitLabel())->toBe('Lata');
});

test('copiar una receta conserva su unidad de producción', function () {
    [$user, $tenant, $product] = productionSetup();
    $product->recipe->update(['production_unit_name' => 'lata', 'production_unit_quantity' => 6]);

    $this->actingAs($user)->post(route('recipes.copy', $product->recipe))->assertRedirect();

    $copy = Recipe::where('name', 'like', '% (copia)')->first();
    expect($copy->production_unit_name)->toBe('lata');
});

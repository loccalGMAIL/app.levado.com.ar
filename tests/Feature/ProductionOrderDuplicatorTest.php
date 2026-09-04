<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\Unit;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Recipe;
use App\Services\ProductionOrderDuplicator;

// stockTenantUser() (StockServiceTest) y manufacturedProduct() (ProductionTest) son helpers globales.

function duplicator(): ProductionOrderDuplicator
{
    return app(ProductionOrderDuplicator::class);
}

test('la copia trae los pedidos y las líneas del original', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);

    $source = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $source->id]);
    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => 8,
        'unit' => $product->unit->value,
    ]);

    $copy = duplicator()->duplicate($source, $user, scheduledFor: '2026-09-10');

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->status)->toBe(ProductionOrderStatus::Draft)
        ->and($copy->is_template)->toBeFalse()
        ->and($copy->scheduled_for->toDateString())->toBe('2026-09-10')
        ->and($copy->confirmed_at)->toBeNull()
        ->and($copy->produced_at)->toBeNull()
        ->and($copy->productionOrderRequests)->toHaveCount(1)
        ->and($copy->productionOrderRequests->first()->lines)->toHaveCount(1)
        ->and($copy->productionOrderRequests->first()->lines->first()->quantity)->toEqual(8.0)
        ->and($copy->productionOrderRequests->first()->lines->first()->product_id)->toBe($product->id);
});

test('editar la copia no toca el original', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);

    $source = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $source->id]);
    $line = ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit' => $product->unit->value,
    ]);

    $copy = duplicator()->duplicate($source, $user);
    $copyLine = $copy->productionOrderRequests->first()->lines->first();
    $copyLine->update(['quantity' => 99]);

    expect($line->fresh()->quantity)->toEqual(3.0);
});

test('duplicate con templateName crea una plantilla sin fecha, excluida del listado normal', function () {
    [$user, $tenant] = stockTenantUser();
    $source = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $template = duplicator()->duplicate($source, $user, templateName: 'Lunes de panadería');

    expect($template->is_template)->toBeTrue()
        ->and($template->scheduled_for)->toBeNull()
        ->and($template->name)->toBe('Lunes de panadería')
        ->and(ProductionOrder::query()->pluck('id'))->not->toContain($template->id)
        ->and(ProductionOrder::onlyTemplates()->pluck('id'))->toContain($template->id);
});

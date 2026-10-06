<?php

use App\Enums\ProductionStatus;
use App\Enums\TenantUserRole;
use App\Services\ProductionService;
use App\Services\StockService;

// tenantUserAs() es global (IngredientCrudTest); manufacturedProduct() y seedStock() son globales (ProductionTest).
// productionSetup() es global — la reusan InstantProductionOrderTest y ProductionOrderControllerTest.

test('anular por el controller revierte el stock y marca la producción', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $production = app(ProductionService::class)->produce($product, 24, null, $user);

    $this->actingAs($user)->patch(route('production.cancel', $production))->assertRedirect();

    expect($production->fresh()->status)->toBe(ProductionStatus::Cancelled)
        ->and((float) app(StockService::class)->levelFor($harina->fresh(), $tenant->defaultLocation())->quantity)->toBe(5000.0);
});

test('no se puede ver la producción de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    [$other, , $otherProduct, $otherHarina] = productionSetup();
    seedStock($otherHarina, 5000, $other);
    $production = app(ProductionService::class)->produce($otherProduct, 12, null, $other);

    $this->actingAs($user)->get(route('production.show', $production))->assertNotFound();
});

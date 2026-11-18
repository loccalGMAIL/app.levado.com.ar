<?php

use App\Enums\ProductType;
use App\Models\Ingredient;
use App\Models\Invitation;
use App\Models\MailTemplate;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Tenant;

// Humo de las factories agregadas por la auditoría (D3). StockMovement no
// tiene factory a propósito: el ledger solo se escribe vía StockService.

test('PurchaseFactory crea una compra con proveedor del mismo tenant', function () {
    $tenant = Tenant::factory()->create();

    $purchase = Purchase::factory()->for($tenant)->create();

    expect($purchase->tenant_id)->toBe($tenant->id)
        ->and($purchase->supplier->tenant_id)->toBe($tenant->id);
});

test('PurchaseLineFactory crea renglones pendientes y asociados', function () {
    $tenant = Tenant::factory()->create();
    $purchase = Purchase::factory()->for($tenant)->create();
    $ingredient = Ingredient::factory()->for($tenant)->create();

    $pending = PurchaseLine::factory()->for($purchase)->create();
    $matched = PurchaseLine::factory()->for($purchase)->matchedToIngredient($ingredient->id)->create();

    expect($pending->isMatched())->toBeFalse()
        ->and($matched->isIngredient())->toBeTrue()
        ->and($matched->purchaseable_id)->toBe($ingredient->id);
});

test('InvitationFactory crea invitaciones vigentes, vencidas y aceptadas', function () {
    $tenant = Tenant::factory()->create();

    $pending = Invitation::factory()->for($tenant)->create();
    $expired = Invitation::factory()->for($tenant)->expired()->create();
    $accepted = Invitation::factory()->for($tenant)->accepted()->create();

    expect($pending->isExpired())->toBeFalse()
        ->and($pending->isAccepted())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue()
        ->and($accepted->isAccepted())->toBeTrue();
});

test('MailTemplateFactory crea una plantilla utilizable', function () {
    $template = MailTemplate::factory()->create();

    expect($template->mail_type)->toBe('team-invitation')
        ->and($template->subject)->not->toBeEmpty();
});

test('ProductFactory crea un producto de reventa con costo propio y sin receta', function () {
    $tenant = Tenant::factory()->create();

    $product = Product::factory()->for($tenant)->resale()->create();

    expect($product->tenant_id)->toBe($tenant->id)
        ->and($product->type)->toBe(ProductType::Resale)
        ->and($product->isResale())->toBeTrue()
        ->and($product->recipe_id)->toBeNull()
        ->and($product->cost_per_unit)->not->toBeNull();
});

test('ProductFactory crea un producto elaborado con receta del mismo tenant y sin costo propio', function () {
    $tenant = Tenant::factory()->create();

    $product = Product::factory()->for($tenant)->manufactured()->create();

    expect($product->tenant_id)->toBe($tenant->id)
        ->and($product->type)->toBe(ProductType::Manufactured)
        ->and($product->isManufactured())->toBeTrue()
        ->and($product->cost_per_unit)->toBeNull()
        ->and($product->recipe)->not->toBeNull()
        ->and($product->recipe->tenant_id)->toBe($tenant->id);
});

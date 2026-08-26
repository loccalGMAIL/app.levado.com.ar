<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Services\Ean13Generator;
use App\Services\ProductCodeAssigner;

test('assignIfMissing asigna un EAN-13 válido a un artículo sin código', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->for($tenant)->create(['barcode' => null]);

    $assigned = app(ProductCodeAssigner::class)->assignIfMissing($product);

    expect($assigned)->toBeTrue();
    $product->refresh();
    expect($product->barcode)->not->toBeNull()
        ->and(app(Ean13Generator::class)->isValid($product->barcode))->toBeTrue();
});

test('no toca un artículo que ya trae un código de barras real', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->for($tenant)->create(['barcode' => '7790001234567']);

    $assigned = app(ProductCodeAssigner::class)->assignIfMissing($product);

    expect($assigned)->toBeFalse()
        ->and($product->fresh()->barcode)->toBe('7790001234567');
});

test('products:assign-codes codifica solo los artículos sin código', function () {
    $tenant = Tenant::factory()->create();
    $sinCodigo = Product::factory()->for($tenant)->create(['barcode' => null]);
    $conCodigo = Product::factory()->for($tenant)->create(['barcode' => '7790000000001']);

    $this->artisan('products:assign-codes')->assertExitCode(0);

    expect($sinCodigo->fresh()->barcode)->not->toBeNull()
        ->and($conCodigo->fresh()->barcode)->toBe('7790000000001');
});

test('products:assign-codes es idempotente', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->for($tenant)->create(['barcode' => null]);

    $this->artisan('products:assign-codes')->assertExitCode(0);
    $code = $product->fresh()->barcode;
    $this->artisan('products:assign-codes')->assertExitCode(0);

    expect($product->fresh()->barcode)->toBe($code);
});

test('products:assign-codes --dry-run no escribe', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->for($tenant)->create(['barcode' => null]);

    $this->artisan('products:assign-codes', ['--dry-run' => true])->assertExitCode(0);

    expect($product->fresh()->barcode)->toBeNull();
});

test('products:assign-codes --tenant limita a un negocio', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $productoA = Product::factory()->for($a)->create(['barcode' => null]);
    $productoB = Product::factory()->for($b)->create(['barcode' => null]);

    $this->artisan('products:assign-codes', ['--tenant' => $a->id])->assertExitCode(0);

    expect($productoA->fresh()->barcode)->not->toBeNull()
        ->and($productoB->fresh()->barcode)->toBeNull();
});

<?php

use App\Models\Ingredient;
use App\Services\InvoiceExtractor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

function fakeModelResponse(array $payload): void
{
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
        ]),
    ]);
}

function harinaIngredient(): Collection
{
    return collect([
        (new Ingredient)->forceFill(['id' => 12, 'name' => 'Harina 000', 'brand' => null, 'unit' => 'kg']),
    ]);
}

it('transcribes lines literally without any calculation', function () {
    // El Sureño: HARINA 3/0 X 25 Kg · 200 bolsas · $13.891,40 — se copia tal cual.
    fakeModelResponse([
        'supplier_name' => 'MOLINO HARINERO EL SUREÑO S.R.L.',
        'invoice_number' => '0012-00017065',
        'invoice_date' => '2026-05-14',
        'total' => 3069999.40,
        'lines' => [[
            'raw_name' => 'HARINA 3/0 X 25 Kg',
            'quantity' => 200,
            'unit' => 'u',
            'unit_price' => 13891.40,
            'matched_type' => 'ingredient',
            'matched_id' => 12,
        ]],
    ]);

    $draft = app(InvoiceExtractor::class)->extract('b64', 'image/jpeg', harinaIngredient(), collect());

    expect($draft['header']['invoice_number'])->toBe('0012-00017065')
        ->and($draft['header']['invoice_date'])->toBe('2026-05-14');

    $line = $draft['lines'][0];
    // Sin pack math, sin IVA: los valores quedan idénticos a la factura.
    expect($line['quantity'])->toBe(200.0)
        ->and($line['unit'])->toBe('u')
        ->and($line['unit_price'])->toBe(13891.40)
        ->and($line['raw_name'])->toBe('HARINA 3/0 X 25 Kg')
        ->and($line['matched_id'])->toBe(12);
});

it('keeps decimal values as read (loose-weight line)', function () {
    fakeModelResponse([
        'lines' => [[
            'raw_name' => 'QUESO TYBO BARRA X kg',
            'quantity' => 146.85,
            'unit' => 'kg',
            'unit_price' => 7822.85,
            'matched_type' => null,
            'matched_id' => null,
        ]],
    ]);

    $line = app(InvoiceExtractor::class)->extract('b64', 'image/jpeg', harinaIngredient(), collect())['lines'][0];

    expect($line['quantity'])->toBe(146.85)
        ->and($line['unit'])->toBe('kg')
        ->and($line['unit_price'])->toBe(7822.85);
});

it('parses Argentine-formatted number strings', function () {
    fakeModelResponse([
        'lines' => [[
            'raw_name' => 'X',
            'quantity' => '1.234',
            'unit' => 'bulto',
            'unit_price' => '13.891,40',
            'matched_type' => null,
            'matched_id' => null,
        ]],
    ]);

    $line = app(InvoiceExtractor::class)->extract('b64', 'image/jpeg', harinaIngredient(), collect())['lines'][0];

    expect($line['unit_price'])->toBe(13891.40)
        ->and($line['unit'])->toBe('u'); // "bulto" → u
});

it('drops a suggested match that is not in the tenant catalog', function () {
    fakeModelResponse([
        'lines' => [[
            'raw_name' => 'PRODUCTO X',
            'quantity' => 1,
            'unit' => 'u',
            'unit_price' => 100,
            'matched_type' => 'ingredient',
            'matched_id' => 999, // no existe en el catálogo
        ]],
    ]);

    $line = app(InvoiceExtractor::class)->extract('b64', 'image/jpeg', harinaIngredient(), collect())['lines'][0];

    expect($line['matched_type'])->toBeNull()
        ->and($line['matched_id'])->toBeNull();
});

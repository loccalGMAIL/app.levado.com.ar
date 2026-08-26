<?php

use App\Services\Ean13Generator;

test('genera un EAN-13 de 13 dígitos con prefijo 2 y verificador válido', function () {
    $gen = new Ean13Generator;

    foreach (range(1, 20) as $_) {
        $code = $gen->generate();
        expect($code)->toMatch('/^\d{13}$/')
            ->and($code[0])->toBe('2')
            ->and($gen->isValid($code))->toBeTrue();
    }
});

test('calcula el dígito verificador EAN-13 estándar', function () {
    $gen = new Ean13Generator;

    // 4006381333931: los 12 dígitos 400638133393 verifican en 1.
    expect($gen->checkDigit('400638133393'))->toBe(1);
});

test('isValid rechaza códigos mal formados o con verificador incorrecto', function () {
    $gen = new Ean13Generator;

    expect($gen->isValid('4006381333930'))->toBeFalse() // verificador debería ser 1
        ->and($gen->isValid('123'))->toBeFalse()
        ->and($gen->isValid('abcdefghijklm'))->toBeFalse();
});

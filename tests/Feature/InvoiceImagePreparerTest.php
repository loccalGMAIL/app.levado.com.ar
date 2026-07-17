<?php

use App\Services\InvoiceImagePreparer;

/**
 * Construye un JPEG con un tamaño dado y, opcionalmente, una etiqueta EXIF de
 * orientación. `UploadedFile::fake()->image()` no genera EXIF, así que los
 * bytes se arman a mano: se inyecta un bloque APP1 mínimo con un único campo
 * Orientation, que es lo que escribe la cámara del teléfono.
 */
function jpegWithOrientation(int $width, int $height, ?int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 250));
    // Una marca asimétrica: si la orientación se aplica, cambia de esquina.
    imagefilledrectangle($image, 0, 0, max(1, (int) ($width / 8)), max(1, (int) ($height / 8)), imagecolorallocate($image, 0, 0, 0));

    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    if ($orientation === null) {
        return $jpeg;
    }

    // TIFF big-endian: header + 1 entrada (Orientation, SHORT, valor).
    $tiff = "\x4D\x4D\x00\x2A\x00\x00\x00\x08\x00\x01"
        ."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"
        ."\x00\x00\x00\x00";
    $exif = "Exif\x00\x00".$tiff;
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

/**
 * @return array{0: int, 1: int}
 */
function jpegSize(string $bytes): array
{
    [$w, $h] = getimagesizefromstring($bytes);

    return [$w, $h];
}

/**
 * En qué esquina quedó la marca negra del fixture. El tamaño no alcanza para
 * ver un giro de 180° ni un espejado: la esquina sí.
 */
function darkCorner(string $bytes): string
{
    $image = imagecreatefromstring($bytes);
    $w = imagesx($image) - 1;
    $h = imagesy($image) - 1;

    $isDark = function (int $x, int $y) use ($image): bool {
        $rgb = imagecolorat($image, $x, $y);

        return (($rgb >> 16) & 0xFF) < 100;
    };

    $inset = 4;
    $corners = [
        'arriba-izq' => [$inset, $inset],
        'arriba-der' => [$w - $inset, $inset],
        'abajo-izq' => [$inset, $h - $inset],
        'abajo-der' => [$w - $inset, $h - $inset],
    ];

    $found = 'ninguna';
    foreach ($corners as $name => [$x, $y]) {
        if ($isDark($x, $y)) {
            $found = $name;
            break;
        }
    }

    imagedestroy($image);

    return $found;
}

test('el fixture arranca con la marca arriba a la izquierda', function () {
    expect(darkCorner(jpegWithOrientation(1200, 900, null)))->toBe('arriba-izq');
});

test('una foto acostada con EXIF se endereza aunque no haya que achicarla', function () {
    // 1200x900 con Orientation=6: el teléfono la muestra vertical.
    $bytes = jpegWithOrientation(1200, 900, 6);

    [$out] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    expect(jpegSize($out))->toBe([900, 1200]);
});

test('una foto grande y acostada se endereza al achicarla', function () {
    // El caso real: 12 MP con Orientation=6. Antes GD la achicaba ignorando el
    // EXIF y lo descartaba al re-encodear, dejándola acostada para siempre.
    $bytes = jpegWithOrientation(4000, 3000, 6);

    [$out] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    [$w, $h] = jpegSize($out);

    expect($h)->toBeGreaterThan($w)
        ->and(max($w, $h))->toBeLessThanOrEqual(1600);
});

test('la orientación 8 gira para el otro lado', function () {
    $bytes = jpegWithOrientation(1200, 900, 8);

    [$out] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    expect(jpegSize($out))->toBe([900, 1200]);
});

test('la orientación 3 da vuelta la foto sin cambiar la forma', function () {
    $bytes = jpegWithOrientation(1200, 900, 3);

    [$out] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    // A 180° el tamaño no cambia: lo que prueba el giro es la marca.
    expect(jpegSize($out))->toBe([1200, 900])
        ->and(darkCorner($out))->toBe('abajo-der');
});

test('cada orientación EXIF deja la marca en su esquina', function (int $orientation, string $esperada) {
    [$out] = app(InvoiceImagePreparer::class)->prepare(jpegWithOrientation(1200, 900, $orientation), 'image/jpeg');

    expect(darkCorner($out))->toBe($esperada);
})->with([
    'sin girar' => [1, 'arriba-izq'],
    'espejada' => [2, 'arriba-der'],
    'de cabeza' => [3, 'abajo-der'],
    'espejada vertical' => [4, 'abajo-izq'],
    'traspuesta' => [5, 'arriba-izq'],
    'girada 90° horario' => [6, 'arriba-der'],
    'transversal' => [7, 'abajo-der'],
    'girada 90° antihorario' => [8, 'abajo-izq'],
]);

test('la orientación 1 no toca la foto', function () {
    $bytes = jpegWithOrientation(1200, 900, 1);

    [$out] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    expect($out)->toBe($bytes);
});

test('una foto sin EXIF sigue pasando intacta', function () {
    $bytes = jpegWithOrientation(1200, 900, null);

    [$out, $mime] = app(InvoiceImagePreparer::class)->prepare($bytes, 'image/jpeg');

    expect($out)->toBe($bytes)
        ->and($mime)->toBe('image/jpeg');
});

test('un PDF no se toca', function () {
    [$out, $mime] = app(InvoiceImagePreparer::class)->prepare('%PDF-1.4 fake', 'application/pdf');

    expect($out)->toBe('%PDF-1.4 fake')
        ->and($mime)->toBe('application/pdf');
});

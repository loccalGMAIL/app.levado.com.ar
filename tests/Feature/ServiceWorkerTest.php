<?php

use Illuminate\Support\Facades\File;

test('el service worker se sirve por ruta, sin auth y con los headers que pide el browser', function () {
    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/javascript');
    // Sin este header el browser rechaza un service worker que no sea un
    // estático en la raíz, y la PWA queda sin registrar.
    $response->assertHeader('Service-Worker-Allowed', '/');
    // El script tiene que revalidarse siempre: si el browser lo sirve de su
    // cache HTTP, una versión nueva puede tardar hasta 24 h en instalarse.
    $response->assertHeader('Cache-Control', 'no-cache, private');
});

test('la version de la app viaja en el cuerpo del script', function () {
    // Es lo único que hace que el browser reinstale el service worker y purgue
    // las caches viejas: si los bytes no cambian entre releases, no hay update.
    $this->get('/sw.js')->assertSee("const VERSION = '".config('app.version')."'", false);
});

test('no queda un sw.js estatico en public que tape la ruta', function () {
    // El servidor web sirve public/ antes de llegar a Laravel: si el archivo
    // sobrevive a un deploy, el service worker versionado nunca se entrega y
    // los clientes siguen con la cache vieja pegada.
    expect(File::exists(public_path('sw.js')))->toBeFalse();
});

test('las fuentes de la version no se desincronizan', function () {
    $package = json_decode(File::get(base_path('package.json')), true);

    expect($package['version'])->toBe(config('app.version'));
});

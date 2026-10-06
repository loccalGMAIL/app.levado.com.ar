<?php

use Illuminate\Support\Facades\Log;

test('un request que supera el umbral se registra como lento', function () {
    config(['app.slow_request_ms' => 0]);
    Log::spy();

    $this->get('/sw.js');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'slow request'
            && $context['path'] === 'sw.js'
            && $context['method'] === 'GET')
        ->once();
});

test('un request rapido no se registra', function () {
    config(['app.slow_request_ms' => 600000]);
    Log::spy();

    $this->get('/sw.js');

    Log::shouldNotHaveReceived('warning');
});

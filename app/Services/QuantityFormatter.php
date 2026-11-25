<?php

namespace App\Services;

/**
 * Cantidades de producción en es-AR con hasta 3 decimales y sin ceros de
 * más (12 · 2,25 · 0,5). Mismo formato que la planilla de pedidos impresa;
 * estático para usarlo directo desde las vistas (pantalla y PDF).
 */
class QuantityFormatter
{
    public static function format(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}

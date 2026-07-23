<?php

namespace App\Enums;

/**
 * Cómo se determina el precio de venta de un artículo en una lista.
 * - Manual: el usuario carga el precio a mano.
 * - Margin: margen sobre el precio → precio = costo / (1 - margen%).
 * - Markup: recargo sobre el costo → precio = costo × (1 + recargo%).
 * El costo es el costo total del artículo (Product::fullCost, con overhead).
 */
enum PricingPolicy: string
{
    case Manual = 'manual';
    case Margin = 'margin';
    case Markup = 'markup';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Margin => 'Margen sobre costo',
            self::Markup => 'Recargo sobre costo',
        };
    }

    /**
     * Precio que resulta de aplicar la política a un costo. Null si no es computable:
     * política manual, costo nulo, o margen ≥ 100%.
     */
    public function priceFor(?float $cost, ?float $value): ?float
    {
        if ($this === self::Manual || $cost === null || $value === null) {
            return null;
        }

        return match ($this) {
            self::Margin => $value < 100 ? round($cost / (1 - $value / 100), 2) : null,
            self::Markup => round($cost * (1 + $value / 100), 2),
            self::Manual => null,
        };
    }
}

<?php

namespace App\Enums;

/**
 * Recipiente en el que se pide un artículo elaborado en las planillas de
 * pedidos (facturas en bandeja, pan en lata). Lo elige cada artículo junto
 * con cuánto entra en uno (products.units_per_container).
 */
enum ContainerType: string
{
    case Tray = 'bandeja';
    case Tin = 'lata';

    public function label(): string
    {
        return match ($this) {
            self::Tray => 'Bandeja',
            self::Tin => 'Lata',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Tray => 'bandejas',
            self::Tin => 'latas',
        };
    }

    /** Abreviatura para celdas y totales de la grilla. */
    public function short(): string
    {
        return match ($this) {
            self::Tray => 'band.',
            self::Tin => 'latas',
        };
    }
}

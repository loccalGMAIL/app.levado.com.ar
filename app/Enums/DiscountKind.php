<?php

namespace App\Enums;

/**
 * Cómo se expresa un descuento de la factura: un porcentaje sobre su base
 * (se recalcula si cambian los renglones) o un importe fijo (cierra contra el papel).
 */
enum DiscountKind: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Porcentaje',
            self::Amount => 'Monto',
        };
    }
}

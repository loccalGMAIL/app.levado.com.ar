<?php

namespace App\Enums;

/**
 * Sobre qué importe de la compra se calcula una percepción automática. Cada
 * régimen fija la suya; la práctica habitual es el neto gravado.
 */
enum PerceptionBase: string
{
    case NetTaxed = 'net_taxed';
    case NetTotal = 'net_total';
    case Gross = 'gross';

    public function label(): string
    {
        return match ($this) {
            self::NetTaxed => 'Neto gravado (renglones con IVA)',
            self::NetTotal => 'Neto total de los renglones',
            self::Gross => 'Total con IVA',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::NetTaxed => 'neto gravado',
            self::NetTotal => 'neto total',
            self::Gross => 'total con IVA',
        };
    }
}

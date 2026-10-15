<?php

namespace App\Enums;

enum PerceptionType: string
{
    case Iva = 'iva';
    case Iibb = 'iibb';
    case Ganancias = 'ganancias';
    case ImpuestosInternos = 'impuestos_internos';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Iva => 'Percepción de IVA',
            self::Iibb => 'Percepción de IIBB',
            self::Ganancias => 'Percepción de Ganancias',
            self::ImpuestosInternos => 'Impuestos internos',
            self::Otro => 'Otro impuesto',
        };
    }

    /** Sólo Ingresos Brutos es provincial: el resto es nacional. */
    public function requiresJurisdiction(): bool
    {
        return $this === self::Iibb;
    }
}

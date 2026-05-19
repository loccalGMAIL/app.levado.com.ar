<?php

namespace App\Enums;

enum CondicionIva: string
{
    case RI = 'RI';
    case MO = 'MO';
    case EX = 'EX';
    case CF = 'CF';
    case NR = 'NR';

    public function label(): string
    {
        return match ($this) {
            self::RI => 'Responsable Inscripto',
            self::MO => 'Monotributista',
            self::EX => 'Exento',
            self::CF => 'Consumidor Final',
            self::NR => 'No Responsable',
        };
    }
}

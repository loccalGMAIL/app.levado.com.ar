<?php

namespace App\Enums;

enum ProductionOrderType: string
{
    case Daily = 'daily';
    case Spontaneous = 'spontaneous';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diaria',
            self::Spontaneous => 'Espontánea',
        };
    }
}

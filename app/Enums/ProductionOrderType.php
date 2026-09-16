<?php

namespace App\Enums;

enum ProductionOrderType: string
{
    case Daily = 'daily';
    case Spontaneous = 'spontaneous';
    case Instant = 'instant';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diaria',
            self::Spontaneous => 'Espontánea',
            self::Instant => 'Instantánea',
        };
    }

    /**
     * Tipos que se pueden elegir al crear una orden a mano. Instant queda
     * afuera: nace únicamente de ProductionOrderService::produceInstant(),
     * nunca del modal de alta — crear una a mano vacía en Draft contradice
     * el sentido del flujo de un solo paso.
     *
     * @return array<int, self>
     */
    public static function selectable(): array
    {
        return [self::Daily, self::Spontaneous];
    }
}

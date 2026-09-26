<?php

namespace App\Enums;

/**
 * Ciclo de estados de una orden de producción. Las transiciones válidas viven
 * en canTransitionTo() — dueño único, para no cazar `if`s repartidos por los
 * controllers si el día de mañana se suma un estado (ej. "despachada").
 */
enum ProductionOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Confirmed => 'Confirmada',
            self::InProduction => 'En producción',
            self::Done => 'Terminada',
            self::Cancelled => 'Anulada',
        };
    }

    /**
     * `InProduction` es un marcador manual ("ya empezamos a hornear esto"), sin
     * efecto en el stock — lo único que mueve stock es producir la orden, que
     * lleva a `Done` directo desde `Confirmed` o desde `InProduction`.
     * `Done` puede pasar a `Cancelled` (anular lo ya producido revierte el
     * stock); de ahí no hay otra salida.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => in_array($next, [self::Draft, self::InProduction, self::Done, self::Cancelled], true),
            self::InProduction => in_array($next, [self::Done, self::Cancelled], true),
            self::Done => $next === self::Cancelled,
            self::Cancelled => false,
        };
    }
}

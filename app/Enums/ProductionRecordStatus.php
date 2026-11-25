<?php

namespace App\Enums;

/**
 * Estado de un registro de producción (amasado o cierre de cámara). El
 * borrador no toca el stock; confirmar escribe los movimientos; anular los
 * revierte con contramovimientos.
 */
enum ProductionRecordStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Confirmed => 'Confirmado',
            self::Cancelled => 'Anulado',
        };
    }

    /** Clases del badge de estado. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-amber-100 text-amber-800',
            self::Confirmed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-gray-100 text-gray-600',
        };
    }
}

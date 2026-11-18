<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Bonus = 'bonus';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Count = 'count';

    // Reservados para etapas futuras (ventas, transferencias entre sucursales).
    case Sale = 'sale';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Bonus => 'Bonificación',
            self::Return => 'Devolución',
            self::Adjustment => 'Ajuste',
            self::Count => 'Recuento',
            self::Sale => 'Venta',
            self::Transfer => 'Transferencia',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment;
    }
}

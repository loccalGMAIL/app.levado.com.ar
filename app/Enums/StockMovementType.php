<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Bonus = 'bonus';
    case Adjustment = 'adjustment';
    case Count = 'count';

    // Reservados para etapas futuras (producción, ventas, transferencias entre sucursales).
    case Production = 'production';
    case Sale = 'sale';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Bonus => 'Bonificación',
            self::Adjustment => 'Ajuste',
            self::Count => 'Recuento',
            self::Production => 'Producción',
            self::Sale => 'Venta',
            self::Transfer => 'Transferencia',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment;
    }
}

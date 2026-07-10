<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';
    case Waste = 'waste';
    case Count = 'count';

    // Reservados para etapas futuras (producción, ventas, transferencias entre sucursales).
    case Production = 'production';
    case Sale = 'sale';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Adjustment => 'Ajuste',
            self::Waste => 'Merma',
            self::Count => 'Recuento',
            self::Production => 'Producción',
            self::Sale => 'Venta',
            self::Transfer => 'Transferencia',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment || $this === self::Waste;
    }
}

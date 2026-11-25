<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Bonus = 'bonus';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Count = 'count';

    // Producción en dos etapas. Amasado: consume insumos y entra el crudo (a
    // cámara) o el terminado (directo). Horneado: traspaso puro cámara →
    // terminado, siempre de a pares, más los descartables. Merma: la única
    // salida por pérdida, con su motivo en `reason`.
    case Dough = 'dough';
    case Bake = 'bake';
    case Waste = 'waste';

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
            self::Dough => 'Amasado',
            self::Bake => 'Horneado',
            self::Waste => 'Merma',
            self::Sale => 'Venta',
            self::Transfer => 'Transferencia',
        };
    }

    public function requiresReason(): bool
    {
        return $this === self::Adjustment || $this === self::Waste;
    }
}

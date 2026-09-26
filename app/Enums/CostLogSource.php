<?php

namespace App\Enums;

/**
 * De dónde salió un costo registrado en el historial de un artículo de reventa.
 * - Purchase: lo imputó una línea de compra (queda el vínculo a la factura).
 * - Manual: lo tipeó alguien en el formulario del artículo.
 */
enum CostLogSource: string
{
    case Purchase = 'compra';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Manual => 'Manual',
        };
    }
}

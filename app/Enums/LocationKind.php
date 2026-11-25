<?php

namespace App\Enums;

/**
 * Tipo de ubicación de stock. La sucursal guarda el terminado y es donde
 * entran las compras; la cámara de leudado guarda el crudo (el mismo artículo
 * base) entre el amasado y el horno. Una cámara nunca es la ubicación default.
 */
enum LocationKind: string
{
    case Store = 'store';
    case ProofingChamber = 'proofing_chamber';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Sucursal',
            self::ProofingChamber => 'Cámara de leudado',
        };
    }
}

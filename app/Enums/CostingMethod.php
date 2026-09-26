<?php

namespace App\Enums;

/**
 * Método de costeo de un producto de reventa: cómo se determina su costo vigente
 * a partir de las compras.
 * - LastCost: el costo es el de la última compra.
 * - WeightedAverage: promedio ponderado entre el stock existente (a su costo) y lo comprado.
 */
enum CostingMethod: string
{
    case LastCost = 'last';
    case WeightedAverage = 'average';

    public function label(): string
    {
        return match ($this) {
            self::LastCost => 'Último costo',
            self::WeightedAverage => 'Promedio ponderado',
        };
    }
}

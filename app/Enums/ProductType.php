<?php

namespace App\Enums;

/**
 * Origen de un producto (artículo) vendible.
 * - Manufactured: se fabrica desde una receta (recipe_id); lo produce Producción.
 * - Resale: se compra para revender; su stock y costo los mantiene Compras.
 */
enum ProductType: string
{
    case Manufactured = 'manufactured';
    case Resale = 'resale';

    public function label(): string
    {
        return match ($this) {
            self::Manufactured => 'Elaborado',
            self::Resale => 'Reventa',
        };
    }

    public function usesRecipe(): bool
    {
        return $this === self::Manufactured;
    }
}

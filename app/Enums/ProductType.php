<?php

namespace App\Enums;

/**
 * Origen de un producto (artículo) vendible.
 * - Manufactured: se fabrica desde una receta (recipe_id); lo produce Producción.
 * - Resale: se compra para revender; su stock y costo los mantiene Compras.
 * - Combo: agrupa otros artículos bajo un código y un precio propios. No tiene
 *   stock ni costo propios: ambos salen de sus componentes (product_combo_lines).
 */
enum ProductType: string
{
    case Manufactured = 'manufactured';
    case Resale = 'resale';
    case Combo = 'combo';

    public function label(): string
    {
        return match ($this) {
            self::Manufactured => 'Elaborado',
            self::Resale => 'Reventa',
            self::Combo => 'Combo',
        };
    }

    public function usesRecipe(): bool
    {
        return $this === self::Manufactured;
    }
}

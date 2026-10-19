<?php

namespace App\Enums;

/**
 * En qué se expresa la cantidad de una línea de pedido: unidades del
 * artículo, o unidades de producción de su receta (lata, tanda). Producción
 * siempre fabrica el artículo base: Product::baseQuantityFor() convierte.
 */
enum LineMeasure: string
{
    case Unit = 'unit';
    case ProductionUnit = 'production_unit';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidades',
            self::ProductionUnit => 'Unidad de producción',
        };
    }
}

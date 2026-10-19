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

    /**
     * Salto de los botones −/+ de la grilla de pedidos: un cuarto de lata,
     * y para las unidades del artículo, un paso acorde a la unidad (de a 1 u,
     * de a medio kilo, de a 50 gr). Único dueño de la tabla — el catálogo la
     * manda a la grilla, que no tiene números propios.
     */
    public function step(Unit $unit): float
    {
        if ($this === self::ProductionUnit) {
            return 0.25;
        }

        return match ($unit) {
            Unit::Unidad => 1.0,
            Unit::Kilogramo, Unit::Litro => 0.5,
            Unit::Gramo, Unit::Mililitro, Unit::Centimetro3 => 50.0,
        };
    }
}

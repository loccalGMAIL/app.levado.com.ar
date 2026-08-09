<?php

namespace App\Enums;

/**
 * Dueño único de los cortes de margen. Antes vivían repartidos en siete lugares
 * con DOS escalas distintas: el dashboard usaba 60/40/20 (vista, tres getters
 * Alpine, filtro SQL y contador de margen bajo) mientras RecipePriceController
 * y la matriz de precios usaban 30/15. Editar un precio inline en el dashboard
 * devolvía el color de la otra escala, así que una receta al 35% pasaba de
 * naranja a verde sola mientras la badge de al lado seguía diciendo «Regular».
 *
 * Los porcentajes se comparan como float: `fromPercent(59.99)` es Media.
 */
enum MarginTier: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Regular = 'regular';
    case Baja = 'baja';

    /** Sin precio de venta o sin costo calculable no hay margen que clasificar. */
    case Indefinido = 'indefinido';

    public const HIGH = 60;

    public const MEDIUM = 40;

    public const LOW = 20;

    public static function fromPercent(?float $pct): self
    {
        return match (true) {
            $pct === null => self::Indefinido,
            $pct >= self::HIGH => self::Alta,
            $pct >= self::MEDIUM => self::Media,
            $pct >= self::LOW => self::Regular,
            default => self::Baja,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Alta => 'Alta',
            self::Media => 'Media',
            self::Regular => 'Regular',
            self::Baja => 'Baja',
            self::Indefinido => '—',
        };
    }

    /** Color del número de margen. */
    public function textColor(): string
    {
        return match ($this) {
            self::Alta => 'text-green-600',
            self::Media => 'text-amber-600',
            self::Regular => 'text-orange-600',
            self::Baja => 'text-red-500',
            self::Indefinido => 'text-masa-madre',
        };
    }
}

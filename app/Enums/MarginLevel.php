<?php

namespace App\Enums;

/**
 * Salud del margen sobre el precio de venta. Única fuente de los umbrales y de
 * los colores con que se pinta un margen: dashboard, catálogo, matriz de precios
 * y ficha de receta leen de acá (antes cada pantalla tenía su propia escala).
 */
enum MarginLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Fair = 'fair';
    case Low = 'low';

    private const NEUTRAL_TEXT = 'text-masa-madre';

    /** Margen (%) desde el que empieza el nivel. */
    public function threshold(): float
    {
        return match ($this) {
            self::High => 60.0,
            self::Medium => 40.0,
            self::Fair => 20.0,
            self::Low => -INF,
        };
    }

    public static function for(?float $marginPct): ?self
    {
        if ($marginPct === null) {
            return null;
        }

        foreach (self::cases() as $level) {
            if ($marginPct >= $level->threshold()) {
                return $level;
            }
        }

        return self::Low;
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta',
            self::Medium => 'Media',
            self::Fair => 'Regular',
            self::Low => 'Baja',
        };
    }

    public function textClass(): string
    {
        return match ($this) {
            self::High => 'text-green-600',
            self::Medium => 'text-amber-600',
            self::Fair => 'text-orange-600',
            self::Low => 'text-red-500',
        };
    }

    public function barClass(): string
    {
        return match ($this) {
            self::High => 'bg-green-500',
            self::Medium => 'bg-amber-500',
            self::Fair => 'bg-orange-400',
            self::Low => 'bg-red-500',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::High => 'bg-green-100 text-green-700',
            self::Medium => 'bg-amber-100 text-amber-700',
            self::Fair => 'bg-orange-100 text-orange-700',
            self::Low => 'bg-red-100 text-red-700',
        };
    }

    /** Clase de texto para un margen, o la neutra si no hay margen calculable. */
    public static function textClassFor(?float $marginPct): string
    {
        return self::for($marginPct)?->textClass() ?? self::NEUTRAL_TEXT;
    }

    /**
     * Expresión JS (para un `:class` / `x-text` de Alpine) que resuelve el mismo
     * nivel en el navegador, sobre la variable `$variable`, devolviendo lo que
     * dé `$method` para cada nivel.
     *
     * @param  'label'|'textClass'|'barClass'|'badgeClass'  $method
     */
    public static function alpineExpression(string $variable, string $method, string $whenNull = ''): string
    {
        $expression = "'".self::Low->{$method}()."'";

        foreach ([self::Fair, self::Medium, self::High] as $level) {
            $expression = "({$variable} >= {$level->threshold()} ? '{$level->{$method}()}' : {$expression})";
        }

        return "({$variable} === null ? '{$whenNull}' : {$expression})";
    }
}

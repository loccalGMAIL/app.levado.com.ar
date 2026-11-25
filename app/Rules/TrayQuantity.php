<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una cantidad de bandejas o latas: no negativa y de a cuartos
 * (1 · 1,25 · 1,5 · 1,75). Dueña única del paso.
 */
class TrayQuantity implements ValidationRule
{
    public const STEP = 0.25;

    public static function isStep(float $value): bool
    {
        return abs($value / self::STEP - round($value / self::STEP)) < 1e-6;
    }

    /** Redondea al cuarto más cercano (para convertir unidades a recipientes). */
    public static function roundToStep(float $value): float
    {
        return round($value / self::STEP) * self::STEP;
    }

    /**
     * Redondea hacia arriba al cuarto (lo que hay que amasar: si faltan 2,1
     * latas se arman 2,25). El redondeo previo evita que 2,0000001 suba a 2,25.
     */
    public static function ceilToStep(float $value): float
    {
        return ceil(round($value / self::STEP, 6)) * self::STEP;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            return; // Lo informan las reglas numeric/min del mismo campo.
        }

        if (! self::isStep((float) $value)) {
            $fail('Las bandejas y latas se cargan de a cuartos (por ejemplo 1, 1,25, 1,5 o 1,75).');
        }
    }
}

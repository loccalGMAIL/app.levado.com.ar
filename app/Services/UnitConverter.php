<?php

namespace App\Services;

use App\Enums\Unit;

class UnitConverter
{
    /**
     * Convert $amount from $from unit to $to unit.
     * Returns null if the units are incompatible (different dimensions).
     */
    public function convert(float $amount, Unit $from, Unit $to): ?float
    {
        if ($from === $to) {
            return $amount;
        }

        if (! $this->compatible($from, $to)) {
            return null;
        }

        return $this->fromBase($this->toBase($amount, $from), $to);
    }

    public function compatible(Unit $a, Unit $b): bool
    {
        return $this->dimension($a) === $this->dimension($b);
    }

    private function dimension(Unit $unit): string
    {
        return match ($unit) {
            Unit::Gramo, Unit::Kilogramo => 'weight',
            Unit::Mililitro, Unit::Litro, Unit::Centimetro3 => 'volume',
            Unit::Unidad => 'unit',
        };
    }

    /**
     * Convert to the base unit of the dimension (gr for weight, ml for volume, u for unit).
     */
    private function toBase(float $amount, Unit $unit): float
    {
        return match ($unit) {
            Unit::Kilogramo => $amount * 1000,
            Unit::Litro => $amount * 1000,
            default => $amount,
        };
    }

    private function fromBase(float $base, Unit $unit): float
    {
        return match ($unit) {
            Unit::Kilogramo => $base / 1000,
            Unit::Litro => $base / 1000,
            default => $base,
        };
    }
}

<?php

namespace App\Enums;

enum Unit: string
{
    case Gramo = 'gr';
    case Kilogramo = 'kg';
    case Mililitro = 'ml';
    case Litro = 'L';
    case Centimetro3 = 'cc';
    case Unidad = 'u';

    public function label(): string
    {
        return match ($this) {
            self::Gramo => 'Gramo (gr)',
            self::Kilogramo => 'Kilogramo (kg)',
            self::Mililitro => 'Mililitro (ml)',
            self::Litro => 'Litro (L)',
            self::Centimetro3 => 'Centímetro cúbico (cc)',
            self::Unidad => 'Unidad (u)',
        };
    }

    public function short(): string
    {
        return $this->value;
    }
}

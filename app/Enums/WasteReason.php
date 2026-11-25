<?php

namespace App\Enums;

/**
 * Motivo de una merma de producción. Cada motivo es de una etapa: lo que se
 * pierde al amasar (antes de entrar al stock) o en el horno (sale de la
 * cámara). «Otro» exige un texto libre.
 */
enum WasteReason: string
{
    case Hydration = 'hydration';
    case Proofing = 'proofing';
    case Misshaped = 'misshaped';
    case DoughOther = 'dough_other';
    case Burnt = 'burnt';
    case Crushed = 'crushed';
    case BadlyStacked = 'badly_stacked';
    case OvenOther = 'oven_other';

    public const STAGE_DOUGH = 'dough';

    public const STAGE_OVEN = 'oven';

    public function label(): string
    {
        return match ($this) {
            self::Hydration => 'Hidratación',
            self::Proofing => 'Leudado',
            self::Misshaped => 'Mal formado',
            self::Burnt => 'Quemado',
            self::Crushed => 'Aplastado',
            self::BadlyStacked => 'Mal apilado',
            self::DoughOther, self::OvenOther => 'Otro',
        };
    }

    /** @return 'dough'|'oven' */
    public function stage(): string
    {
        return match ($this) {
            self::Hydration, self::Proofing, self::Misshaped, self::DoughOther => self::STAGE_DOUGH,
            self::Burnt, self::Crushed, self::BadlyStacked, self::OvenOther => self::STAGE_OVEN,
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::DoughOther || $this === self::OvenOther;
    }

    /** Texto para el ledger y los listados: el motivo, o «Otro: …» con la nota. */
    public function describe(?string $note): string
    {
        return $this->requiresNote() && filled($note) ? "Otro: {$note}" : $this->label();
    }

    /**
     * Motivos de una etapa, en orden de pantalla.
     *
     * @param  'dough'|'oven'  $stage
     * @return list<self>
     */
    public static function forStage(string $stage): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason) => $reason->stage() === $stage));
    }
}

<?php

namespace App\Enums;

/**
 * Tema visual que eligió el usuario (`users.theme`). `null` es «Automático»: sigue
 * al sistema operativo entre Claro y Oscuro. Los colores de cada tema viven en
 * resources/css/app.css; acá sólo está lo que el servidor necesita saber.
 */
enum Theme: string
{
    case Claro = 'claro';
    case Oscuro = 'oscuro';
    case Centeno = 'centeno';

    public function label(): string
    {
        return match ($this) {
            self::Claro => 'Claro',
            self::Oscuro => 'Oscuro',
            self::Centeno => 'Centeno',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Claro => 'Tonos de harina y corteza.',
            self::Oscuro => 'Fondo oscuro cálido, para poca luz.',
            self::Centeno => 'Neutro frío con acento oliva.',
        };
    }

    /** Ícono de x-icon para el selector compacto del menú de usuario. */
    public function icon(): string
    {
        return match ($this) {
            self::Claro => 'sun',
            self::Oscuro => 'moon',
            self::Centeno => 'swatch',
        };
    }

    /**
     * Color de la barra del navegador y de la PWA (`<meta name="theme-color">`).
     * Tiene que ser hex: el meta no entiende variables CSS. Es el fondo
     * (`--c-harina`) del tema; ThemeTokensTest verifica que no se desincronicen.
     */
    public function themeColor(): string
    {
        return match ($this) {
            self::Claro => '#FAF7F2',
            self::Oscuro => '#16100B',
            self::Centeno => '#F2F3EF',
        };
    }

    /**
     * @return array<string, string> valor => color de la barra, para el script del <head>
     */
    public static function themeColors(): array
    {
        return array_column(
            array_map(fn (self $theme) => ['value' => $theme->value, 'color' => $theme->themeColor()], self::cases()),
            'color',
            'value',
        );
    }
}

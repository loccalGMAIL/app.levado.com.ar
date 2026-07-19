<?php

namespace App\Enums;

enum NotificationType: string
{
    case LowStock = 'low_stock';
    case CostSpike = 'cost_spike';
    case StaleCost = 'stale_cost';
    case UnappliedPurchase = 'unapplied_purchase';

    public function label(): string
    {
        return match ($this) {
            self::LowStock => 'Stock bajo',
            self::CostSpike => 'Salto de costo',
            self::StaleCost => 'Costo desactualizado',
            self::UnappliedPurchase => 'Compra sin imputar',
        };
    }

    /**
     * Clave del toggle en tenant_settings (alerts.<key>.enabled).
     */
    public function settingKey(): string
    {
        return $this->value;
    }

    public function severity(): string
    {
        return match ($this) {
            self::LowStock => 'red',
            self::CostSpike => 'amber',
            self::StaleCost => 'amber',
            self::UnappliedPurchase => 'blue',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LowStock => '📦',
            self::CostSpike => '📈',
            self::StaleCost => '🕒',
            self::UnappliedPurchase => '🧾',
        };
    }
}

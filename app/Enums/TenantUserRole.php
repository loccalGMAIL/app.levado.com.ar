<?php

namespace App\Enums;

enum TenantUserRole: string
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Admin = 'admin';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Owner => 'Propietario',
            self::Admin => 'Administrador',
            self::Viewer => 'Solo lectura',
        };
    }

    public function canManageTenant(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Owner, self::Admin]);
    }

    public function canEditSettings(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Owner]);
    }
}

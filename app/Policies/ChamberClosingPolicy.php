<?php

namespace App\Policies;

use App\Models\ChamberClosing;
use App\Models\Tenant;
use App\Models\User;

/**
 * Aislamiento por negocio. Quién registra (owner/admin) lo decide el
 * middleware de rol de las rutas.
 */
class ChamberClosingPolicy
{
    public function view(User $user, ChamberClosing $chamberClosing): bool
    {
        return $chamberClosing->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, ChamberClosing $chamberClosing): bool
    {
        return $chamberClosing->tenant_id === app(Tenant::class)->id;
    }
}

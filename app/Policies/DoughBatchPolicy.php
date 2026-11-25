<?php

namespace App\Policies;

use App\Models\DoughBatch;
use App\Models\Tenant;
use App\Models\User;

/**
 * Aislamiento por negocio. Quién puede registrar (owner/admin) lo decide el
 * middleware de rol de las rutas; el estado (borrador, confirmado) lo cuida
 * DoughBatchService.
 */
class DoughBatchPolicy
{
    public function view(User $user, DoughBatch $doughBatch): bool
    {
        return $doughBatch->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, DoughBatch $doughBatch): bool
    {
        return $doughBatch->tenant_id === app(Tenant::class)->id;
    }
}

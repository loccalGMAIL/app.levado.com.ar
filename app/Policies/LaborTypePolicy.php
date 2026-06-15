<?php

namespace App\Policies;

use App\Models\LaborType;
use App\Models\Tenant;
use App\Models\User;

class LaborTypePolicy
{
    public function view(User $user, LaborType $laborType): bool
    {
        return $laborType->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, LaborType $laborType): bool
    {
        return $laborType->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, LaborType $laborType): bool
    {
        return $laborType->tenant_id === app(Tenant::class)->id;
    }
}

<?php

namespace App\Policies;

use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;

class ProductionPolicy
{
    public function view(User $user, Production $production): bool
    {
        return $production->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Production $production): bool
    {
        return $production->tenant_id === app(Tenant::class)->id;
    }
}

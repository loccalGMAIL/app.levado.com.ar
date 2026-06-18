<?php

namespace App\Policies;

use App\Models\Packaging;
use App\Models\Tenant;
use App\Models\User;

class PackagingPolicy
{
    public function view(User $user, Packaging $packaging): bool
    {
        return $packaging->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Packaging $packaging): bool
    {
        return $packaging->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Packaging $packaging): bool
    {
        return $packaging->tenant_id === app(Tenant::class)->id;
    }
}

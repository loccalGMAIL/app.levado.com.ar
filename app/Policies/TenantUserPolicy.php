<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

class TenantUserPolicy
{
    public function view(User $user, TenantUser $tenantUser): bool
    {
        return $tenantUser->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, TenantUser $tenantUser): bool
    {
        return $tenantUser->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, TenantUser $tenantUser): bool
    {
        return $tenantUser->tenant_id === app(Tenant::class)->id;
    }
}

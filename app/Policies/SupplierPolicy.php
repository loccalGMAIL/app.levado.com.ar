<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;

class SupplierPolicy
{
    public function view(User $user, Supplier $supplier): bool
    {
        return $supplier->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $supplier->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $supplier->tenant_id === app(Tenant::class)->id;
    }
}

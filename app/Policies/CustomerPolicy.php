<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $customer->tenant_id === app(Tenant::class)->id;
    }
}

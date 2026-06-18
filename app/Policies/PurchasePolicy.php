<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\Tenant;
use App\Models\User;

class PurchasePolicy
{
    public function view(User $user, Purchase $purchase): bool
    {
        return $purchase->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $purchase->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $purchase->tenant_id === app(Tenant::class)->id;
    }
}

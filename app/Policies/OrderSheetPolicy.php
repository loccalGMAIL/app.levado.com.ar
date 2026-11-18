<?php

namespace App\Policies;

use App\Models\OrderSheet;
use App\Models\Tenant;
use App\Models\User;

class OrderSheetPolicy
{
    public function view(User $user, OrderSheet $orderSheet): bool
    {
        return $orderSheet->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, OrderSheet $orderSheet): bool
    {
        return $orderSheet->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, OrderSheet $orderSheet): bool
    {
        return $orderSheet->tenant_id === app(Tenant::class)->id;
    }
}

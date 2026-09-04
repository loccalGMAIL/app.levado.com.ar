<?php

namespace App\Policies;

use App\Models\ProductionOrder;
use App\Models\Tenant;
use App\Models\User;

class ProductionOrderPolicy
{
    public function view(User $user, ProductionOrder $productionOrder): bool
    {
        return $productionOrder->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, ProductionOrder $productionOrder): bool
    {
        return $productionOrder->tenant_id === app(Tenant::class)->id;
    }
}

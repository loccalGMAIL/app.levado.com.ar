<?php

namespace App\Policies;

use App\Models\FixedCost;
use App\Models\Tenant;
use App\Models\User;

class FixedCostPolicy
{
    public function view(User $user, FixedCost $fixedCost): bool
    {
        return $fixedCost->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, FixedCost $fixedCost): bool
    {
        return $fixedCost->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, FixedCost $fixedCost): bool
    {
        return $fixedCost->tenant_id === app(Tenant::class)->id;
    }
}

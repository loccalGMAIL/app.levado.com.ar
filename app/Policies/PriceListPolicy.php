<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\Tenant;
use App\Models\User;

class PriceListPolicy
{
    public function view(User $user, PriceList $priceList): bool
    {
        return $priceList->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $priceList->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $priceList->tenant_id === app(Tenant::class)->id;
    }
}

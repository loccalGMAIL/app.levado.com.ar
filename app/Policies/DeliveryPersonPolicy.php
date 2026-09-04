<?php

namespace App\Policies;

use App\Models\DeliveryPerson;
use App\Models\Tenant;
use App\Models\User;

class DeliveryPersonPolicy
{
    public function view(User $user, DeliveryPerson $deliveryPerson): bool
    {
        return $deliveryPerson->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, DeliveryPerson $deliveryPerson): bool
    {
        return $deliveryPerson->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, DeliveryPerson $deliveryPerson): bool
    {
        return $deliveryPerson->tenant_id === app(Tenant::class)->id;
    }
}

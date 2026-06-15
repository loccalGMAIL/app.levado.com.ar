<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;

class LocationPolicy
{
    public function view(User $user, Location $location): bool
    {
        return $location->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Location $location): bool
    {
        return $location->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Location $location): bool
    {
        return $location->tenant_id === app(Tenant::class)->id;
    }
}

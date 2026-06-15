<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

class InvitationPolicy
{
    public function view(User $user, Invitation $invitation): bool
    {
        return $invitation->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $invitation->tenant_id === app(Tenant::class)->id;
    }
}

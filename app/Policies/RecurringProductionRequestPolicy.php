<?php

namespace App\Policies;

use App\Models\RecurringProductionRequest;
use App\Models\Tenant;
use App\Models\User;

class RecurringProductionRequestPolicy
{
    public function view(User $user, RecurringProductionRequest $recurringProductionRequest): bool
    {
        return $recurringProductionRequest->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, RecurringProductionRequest $recurringProductionRequest): bool
    {
        return $recurringProductionRequest->tenant_id === app(Tenant::class)->id;
    }
}

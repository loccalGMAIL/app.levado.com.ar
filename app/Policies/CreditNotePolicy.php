<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\Tenant;
use App\Models\User;

class CreditNotePolicy
{
    public function view(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $creditNote->tenant_id === app(Tenant::class)->id;
    }
}

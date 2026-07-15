<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VariableExpense;

class VariableExpensePolicy
{
    public function view(User $user, VariableExpense $variableExpense): bool
    {
        return $variableExpense->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, VariableExpense $variableExpense): bool
    {
        return $variableExpense->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, VariableExpense $variableExpense): bool
    {
        return $variableExpense->tenant_id === app(Tenant::class)->id;
    }
}

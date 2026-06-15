<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\User;

class IngredientPolicy
{
    public function view(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $ingredient->tenant_id === app(Tenant::class)->id;
    }
}

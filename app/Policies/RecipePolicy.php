<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\User;

class RecipePolicy
{
    public function view(User $user, Recipe $recipe): bool
    {
        return $recipe->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $recipe->tenant_id === app(Tenant::class)->id;
    }
}

<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $product->tenant_id === app(Tenant::class)->id;
    }

    public function update(User $user, Product $product): bool
    {
        return $product->tenant_id === app(Tenant::class)->id;
    }

    public function delete(User $user, Product $product): bool
    {
        return $product->tenant_id === app(Tenant::class)->id;
    }
}

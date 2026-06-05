<?php

namespace App\Http\Middleware;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $tenant = app(Tenant::class);

        // Super admins impersonating a tenant bypass role restrictions
        if ($user->isSuperAdmin() && $request->session()->has('impersonating_tenant_id')) {
            return $next($request);
        }

        $requiredRoles = array_map(
            fn (string $role) => TenantUserRole::from($role),
            $roles,
        );

        if (! $user->hasRoleInTenant($tenant, ...$requiredRoles)) {
            abort(403);
        }

        return $next($request);
    }
}

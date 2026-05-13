<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            return redirect()->route('login');
        }

        if (! $tenant->active) {
            abort(403, 'Tenant inactivo.');
        }

        App::instance(Tenant::class, $tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        // Solo TenantUsers activos pueden resolver el tenant
        $tenantId = $user->tenantUsers()->where('active', true)->value('tenant_id');

        if ($tenantId === null) {
            return null;
        }

        return Tenant::find($tenantId);
    }
}

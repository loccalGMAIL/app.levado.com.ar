<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
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

        if (! $user instanceof User) {
            return null;
        }

        // Sin nested .tenant a propósito: el objeto Tenant queda cacheado en la
        // relación en memoria del modelo User, que puede sobrevivir más de un
        // request (tests, workers de larga vida) y quedar desactualizado frente
        // a cambios escritos por otra instancia de Tenant. Tenant::find() abajo
        // siempre lee fresco.
        $user->loadMissing('tenantUsers');

        // Super admins pueden impersonar otro tenant desde el backoffice
        if ($user->isSuperAdmin() && $request->session()->has('impersonating_tenant_id')) {
            return Tenant::find($request->session()->get('impersonating_tenant_id'));
        }

        // sortBy garantiza un resultado determinista si el usuario pertenece
        // a más de un tenant (el selector de tenant aún no existe).
        $tenantId = $user->tenantUsers
            ->where('active', true)
            ->sortBy('tenant_id')
            ->first()
            ?->tenant_id;

        return $tenantId !== null ? Tenant::find($tenantId) : null;
    }
}

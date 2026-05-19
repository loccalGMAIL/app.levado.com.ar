<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->session()->put('impersonating_tenant_id', $tenant->id);
        $request->session()->put('impersonating_by_user_id', $request->user()->id);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant',
            targetId: $tenant->id,
            action: 'impersonation.started',
        );

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $tenantId = $request->session()->get('impersonating_tenant_id');

        if ($tenantId) {
            $this->recorder->record(
                actor: $request->user(),
                targetType: 'tenant',
                targetId: $tenantId,
                action: 'impersonation.stopped',
            );
        }

        $request->session()->forget(['impersonating_tenant_id', 'impersonating_by_user_id']);

        return redirect()->route('admin.dashboard');
    }
}

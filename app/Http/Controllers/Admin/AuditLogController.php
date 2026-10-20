<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $from = request('from');
        $to = request('to');

        $logs = AdminAuditLog::with('actor', 'tenant')
            ->when(request('actor_id'), fn ($q, $id) => $q->where('actor_user_id', $id))
            ->when(request('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->when(request('target_type'), fn ($q, $type) => $q->where('target_type', $type))
            ->when(request('action'), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when($from && strtotime($from), fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to && strtotime($to), fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when(request('was_impersonating') !== null && request('was_impersonating') !== '', fn ($q) => $q->where('was_impersonating', (bool) request('was_impersonating')))
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                'created_at' => 'created_at',
                'tenant' => Tenant::select('name')->whereColumn('tenants.id', 'admin_audit_logs.tenant_id'),
                'actor' => User::select('name')->whereColumn('users.id', 'admin_audit_logs.actor_user_id'),
                'action' => 'action',
                'target' => fn (Builder $q, string $dir) => $q->orderBy('target_type', $dir)->orderBy('target_id', $dir),
                'ip_address' => 'ip_address',
                'was_impersonating' => 'was_impersonating',
            ], fn (Builder $q) => $q->latest(), 'admin_audit_logs.id'))
            ->paginate($this->perPage())
            ->withQueryString();

        $actors = User::orderBy('name')->get(['id', 'name']);
        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return view('admin.audit-logs.index', compact('logs', 'actors', 'tenants'));
    }
}

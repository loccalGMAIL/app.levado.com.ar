<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $logs = AdminAuditLog::with('actor')
            ->when(request('actor_id'), fn ($q, $id) => $q->where('actor_user_id', $id))
            ->when(request('target_type'), fn ($q, $type) => $q->where('target_type', $type))
            ->when(request('action'), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->when(request('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when(request('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actors = User::whereHas('tenantUsers', fn ($q) => $q->where('role', 'super_admin'))->get(['id', 'name']);

        return view('admin.audit-logs.index', compact('logs', 'actors'));
    }
}

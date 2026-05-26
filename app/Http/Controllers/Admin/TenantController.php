<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Mail\TeamInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenants = Tenant::withCount(['tenantUsers as users_count', 'tenantUsers as active_users_count' => function ($q) {
            $q->where('active', true);
        }])
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('country', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('admin.tenants.create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create($request->safe()->except('owner_email', 'invitation_message'));

        if ($request->filled('invitation_message')) {
            $tenant->setSetting('invitation_message', $request->validated('invitation_message'));
        }

        $invitation = Invitation::create([
            'tenant_id' => $tenant->id,
            'email' => $request->validated('owner_email'),
            'role' => 'owner',
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);

        Mail::to($invitation->email)->send(new TeamInvitation($invitation));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant',
            targetId: $tenant->id,
            action: 'tenant.created',
            payload: ['name' => $tenant->name, 'owner_email' => $invitation->email],
            tenantId: $tenant->id,
        );

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('status', "Tenant «{$tenant->name}» creado. Invitación enviada a {$invitation->email}.");
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['tenantUsers.user']);

        $metrics = [
            'active_users' => $tenant->tenantUsers()->where('active', true)->count(),
            'total_users' => $tenant->tenantUsers()->count(),
            'pending_invitations' => $tenant->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
        ];

        return view('admin.tenants.show', compact('tenant', 'metrics'));
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant',
            targetId: $tenant->id,
            action: 'tenant.updated',
            payload: $request->validated(),
            tenantId: $tenant->id,
        );

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('status', 'Configuración actualizada.');
    }

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['active' => ! $tenant->active]);

        $action = $tenant->active ? 'tenant.activated' : 'tenant.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'tenant',
            targetId: $tenant->id,
            action: $action,
            tenantId: $tenant->id,
        );

        $label = $tenant->active ? 'activado' : 'desactivado';

        return back()->with('status', "Tenant «{$tenant->name}» {$label}.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $users = User::with(['tenantUsers.tenant'])
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return view('admin.users.index', compact('users', 'tenants'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->validated('tenant_id'));

        $userExists = User::where('email', $request->validated('email'))->exists();

        $user = User::firstOrCreate(
            ['email' => $request->validated('email')],
            ['name' => $request->validated('name'), 'password' => Str::random(32)],
        );

        if (TenantUser::where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists()) {
            return back()
                ->withInput()
                ->with('error', "El usuario {$user->email} ya pertenece a {$tenant->name}.");
        }

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $request->validated('role'),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'user',
            targetId: $user->id,
            action: $userExists ? 'user.tenant_associated' : 'user.created',
            payload: ['email' => $user->email, 'tenant' => $tenant->name, 'role' => $request->validated('role')],
            tenantId: $tenant->id,
        );

        $action = $userExists ? 'asociado a' : 'creado y asociado a';

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario {$user->email} {$action} {$tenant->name}. Se envió el correo para establecer la contraseña.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'user',
            targetId: $user->id,
            action: 'user.updated',
            payload: ['name' => $user->name, 'email' => $user->email],
        );

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario {$user->email} actualizado.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $user->update(['active' => ! $user->active]);

        $action = $user->active ? 'user.activated' : 'user.deactivated';

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'user',
            targetId: $user->id,
            action: $action,
            payload: ['email' => $user->email],
        );

        $label = $user->active ? 'activado' : 'desactivado';

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario {$user->email} {$label}.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', "Correo de recuperación enviado a {$user->email}.");
        }

        return back()->with('error', "No se pudo enviar el correo a {$user->email}. Intente de nuevo más tarde.");
    }
}

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
use Illuminate\Validation\Rules\Password as PasswordRule;
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
            ->paginate($this->perPage())
            ->withQueryString();

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return view('admin.users.index', compact('users', 'tenants'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $tenant = Tenant::findOrFail($request->validated('tenant_id'));

        $userExists = User::where('email', $request->validated('email'))->exists();

        $password = $request->validated('password');

        $user = User::firstOrCreate(
            ['email' => $request->validated('email')],
            ['name' => $request->validated('name'), 'password' => $password ?? Str::random(32)],
        );

        if ($user->wasRecentlyCreated && $password !== null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

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

        $sendsResetLink = $password === null;

        if ($sendsResetLink) {
            Password::sendResetLink(['email' => $user->email]);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'user',
            targetId: $user->id,
            action: $userExists ? 'user.tenant_associated' : 'user.created',
            payload: [
                'email' => $user->email,
                'tenant' => $tenant->name,
                'role' => $request->validated('role'),
                'password_set_by_admin' => $password !== null && ! $userExists,
            ],
            tenantId: $tenant->id,
        );

        $action = $userExists ? 'asociado a' : 'creado y asociado a';

        $message = "Usuario {$user->email} {$action} {$tenant->name}.";

        if ($sendsResetLink) {
            $message .= ' Se envió el correo para establecer la contraseña.';
        } elseif (! $userExists) {
            $message .= ' Ya puede ingresar con la contraseña asignada.';
        }

        return back(fallback: route('admin.users.index'))->with('status', $message);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validateWithBag('updateUser', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
        ]);

        $passwordChanged = ($validated['password'] ?? null) !== null;

        $user->update(array_filter($validated, fn ($value) => $value !== null));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'user',
            targetId: $user->id,
            action: 'user.updated',
            payload: ['name' => $user->name, 'email' => $user->email, 'password_changed' => $passwordChanged],
        );

        return back(fallback: route('admin.users.index'))
            ->with('status', $passwordChanged
                ? "Usuario {$user->email} actualizado. Se cambió su contraseña."
                : "Usuario {$user->email} actualizado.");
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

        return back(fallback: route('admin.users.index'))
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

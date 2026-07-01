<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\InviteTeamMemberRequest;
use App\Mail\TeamInvitation;
use App\Mail\WelcomeMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function store(InviteTeamMemberRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $email = $request->validated('email');

        $alreadyMember = TenantUser::whereHas('user', fn ($q) => $q->where('email', $email))
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($alreadyMember) {
            return back()->withErrors(['email' => 'Este usuario ya pertenece al equipo.']);
        }

        $invitation = Invitation::updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => $email],
            [
                'role' => $request->validated('role'),
                'token' => Str::random(64),
                'accepted_at' => null,
                'expires_at' => now()->addHours(24),
            ],
        );

        Mail::to($email)->send(new TeamInvitation($invitation));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'invitation',
            targetId: $invitation->id,
            action: 'invitation.sent',
            payload: ['email' => $email, 'role' => $invitation->role],
            tenantId: $tenant->id,
        );

        return back()->with('status', "Invitación enviada a {$email}.");
    }

    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación expiró.']);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('status', 'Esta invitación ya fue aceptada.');
        }

        $userExists = User::where('email', $invitation->email)->exists();

        return view('auth.accept-invitation', compact('invitation', 'userExists'));
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        Log::info('invitation.accept.start', ['token' => substr($token, 0, 8)]);

        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired() || $invitation->isAccepted()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación no es válida.']);
        }

        try {
            $user = User::firstOrCreate(
                ['email' => $invitation->email],
                [
                    'name' => $request->validated('name'),
                    'password' => $request->validated('password'),
                ],
            );

            Log::info('invitation.accept.user', ['user_id' => $user->id, 'new' => $user->wasRecentlyCreated]);

            if ($user->wasRecentlyCreated) {
                $user->email_verified_at = now();
                $user->save();
                Mail::to($user->email)->send(new WelcomeMail($user, $invitation->tenant));
            }

            TenantUser::firstOrCreate(
                ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'active' => true],
            );

            $invitation->update(['accepted_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('invitation.accept.failed', ['token' => substr($token, 0, 8), 'error' => $e->getMessage()]);
            throw $e;
        }

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'invitation',
            targetId: $invitation->id,
            action: 'invitation.cancelled',
            payload: ['email' => $invitation->email, 'role' => $invitation->role],
            tenantId: $invitation->tenant_id,
        );

        $invitation->delete();

        return back()->with('status', 'Invitación cancelada.');
    }
}

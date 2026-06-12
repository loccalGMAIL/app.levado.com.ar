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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación expiró.']);
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')->with('status', 'Esta invitación ya fue aceptada.');
        }

        $existingUser = User::where('email', $invitation->email)->first();

        // An existing account can only be linked by its own owner while signed in.
        // Anyone else must authenticate first — we never log in by token alone.
        $mustLogin = $existingUser !== null && $request->user()?->id !== $existingUser->id;

        if ($mustLogin) {
            $request->session()->put('url.intended', route('invitations.accept', $token));
        }

        return view('auth.accept-invitation', [
            'invitation' => $invitation,
            'userExists' => $existingUser !== null,
            'mustLogin' => $mustLogin,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired() || $invitation->isAccepted()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación no es válida.']);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser !== null) {
            // Linking an existing account requires being authenticated as that
            // user — the invitation token alone never grants a session.
            if ($request->user()?->id !== $existingUser->id) {
                $request->session()->put('url.intended', route('invitations.accept', $token));

                return redirect()->route('login')
                    ->with('status', "Iniciá sesión con {$invitation->email} para aceptar la invitación.");
            }

            $user = $existingUser;
        } else {
            $user = User::create([
                'email' => $invitation->email,
                'name' => $request->validated('name'),
                'password' => $request->validated('password'),
            ]);

            $user->email_verified_at = now();
            $user->save();

            Mail::to($user->email)->send(new WelcomeMail($user, $invitation->tenant));
        }

        TenantUser::firstOrCreate(
            ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id],
            ['role' => $invitation->role, 'active' => true],
        );

        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->tenant_id === app(Tenant::class)->id, 403);

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

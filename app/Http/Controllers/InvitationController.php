<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\InviteTeamMemberRequest;
use App\Mail\TeamInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvitationController extends Controller
{
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
                'expires_at' => now()->addDays(7),
            ],
        );

        Mail::to($email)->send(new TeamInvitation($invitation));

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
        $invitation = Invitation::where('token', $token)->firstOrFail();

        if ($invitation->isExpired() || $invitation->isAccepted()) {
            return redirect()->route('login')->withErrors(['email' => 'La invitación no es válida.']);
        }

        $user = User::firstOrCreate(
            ['email' => $invitation->email],
            [
                'name' => $request->validated('name'),
                'password' => bcrypt($request->validated('password')),
            ],
        );

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
        $invitation->delete();

        return back()->with('status', 'Invitación cancelada.');
    }
}

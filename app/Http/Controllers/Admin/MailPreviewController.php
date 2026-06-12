<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Mail\TeamInvitation;
use App\Mail\WelcomeMail;
use App\Models\Invitation;
use App\Models\MailTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MailPreviewController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $templates = MailTemplate::whereIn('mail_type', ['team-invitation', 'welcome'])
            ->get()
            ->keyBy('mail_type');

        return view('admin.mails.index', compact('templates'));
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $allowed = ['team-invitation', 'welcome'];

        if (! in_array($type, $allowed)) {
            abort(404);
        }

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string', 'max:1000'],
            'footer_note' => ['nullable', 'string', 'max:255'],
        ]);

        $template = MailTemplate::updateOrCreate(
            ['mail_type' => $type],
            $validated,
        );

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'mail_template',
            targetId: $template->id,
            action: 'mail_template.updated',
            payload: ['mail_type' => $type],
        );

        return redirect()->route('admin.mails.index')
            ->with('status', 'Plantilla de email actualizada.');
    }

    public function teamInvitation(): TeamInvitation
    {
        $tenant = new Tenant(['name' => 'Panaderia El Trigo de Oro']);
        $tenant->id = 0;

        $invitation = new Invitation([
            'email' => 'ejemplo@correo.com',
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);
        $invitation->setRelation('tenant', $tenant);
        $invitation->role = TenantUserRole::Admin;

        return (new TeamInvitation($invitation))->with([
            'customMessage' => 'Nos alegra sumarte al equipo. Ante cualquier consulta, escribinos directamente.',
        ]);
    }

    public function welcome(): WelcomeMail
    {
        $tenant = new Tenant(['name' => 'Panaderia El Trigo de Oro']);
        $user = new User(['name' => 'Maria Gonzalez']);

        return new WelcomeMail($user, $tenant);
    }
}

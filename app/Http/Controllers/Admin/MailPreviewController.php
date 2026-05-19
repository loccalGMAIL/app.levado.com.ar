<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Mail\TeamInvitation;
use App\Mail\WelcomeMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MailPreviewController extends Controller
{
    public function index(): View
    {
        return view('admin.mails.index');
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

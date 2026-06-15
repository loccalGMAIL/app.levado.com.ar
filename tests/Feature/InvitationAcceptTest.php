<?php

use App\Enums\TenantUserRole;
use App\Mail\WelcomeMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function pendingInvitation(string $email = 'nuevo@levado.test'): Invitation
{
    $tenant = Tenant::factory()->create();

    return Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => $email,
        'role' => TenantUserRole::Viewer->value,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);
}

test('nuevo usuario recibe el welcome email al aceptar la invitación', function () {
    Mail::fake();

    $invitation = pendingInvitation('ana@levado.test');

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Ana García',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ])->assertRedirect(route('dashboard'));

    Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->hasTo('ana@levado.test'));
});

test('nuevo usuario creado via invitación tiene email_verified_at seteado', function () {
    Mail::fake();

    $invitation = pendingInvitation('ana@levado.test');

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Ana García',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ]);

    $user = User::where('email', 'ana@levado.test')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();
});

test('usuario existente no recibe welcome email al aceptar una nueva invitación', function () {
    Mail::fake();

    User::factory()->create(['email' => 'existente@levado.test']);
    $invitation = pendingInvitation('existente@levado.test');

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Nombre Ignorado',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ]);

    Mail::assertNotSent(WelcomeMail::class);
});

test('aceptar la invitación la marca como aceptada', function () {
    Mail::fake();

    $invitation = pendingInvitation();

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Usuario Test',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ]);

    expect($invitation->fresh()->isAccepted())->toBeTrue();
});

test('aceptar la invitación vincula al usuario con el tenant', function () {
    Mail::fake();

    $invitation = pendingInvitation();

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Usuario Test',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ]);

    $user = User::where('email', $invitation->email)->firstOrFail();
    expect(
        TenantUser::where('tenant_id', $invitation->tenant_id)
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();
});

test('invitación expirada no puede aceptarse', function () {
    $tenant = Tenant::factory()->create();
    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'test@levado.test',
        'role' => TenantUserRole::Viewer->value,
        'token' => Str::random(64),
        'expires_at' => now()->subDay(),
    ]);

    $this->post("/invitations/{$invitation->token}", [
        'name' => 'Test',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ])->assertRedirect(route('login'));
});

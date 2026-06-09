<?php

use App\Enums\TenantUserRole;
use App\Mail\TeamInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function ownerForInvitationMessage(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('owner puede guardar el mensaje personalizado de invitación', function () {
    [$user, $tenant] = ownerForInvitationMessage();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country ?? 'AR',
            'currency' => $tenant->currency ?? 'ARS',
            'productive_hours_month' => $tenant->productive_hours_month ?? 160,
            'invitation_message' => 'Bienvenido al equipo, escribinos cuando quieras.',
            'purchase_price_includes_iva' => '1',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->getSetting('invitation_message'))
        ->toBe('Bienvenido al equipo, escribinos cuando quieras.');
});

test('mensaje personalizado aparece en el email de invitación enviado', function () {
    Mail::fake();

    [$user, $tenant] = ownerForInvitationMessage();
    $tenant->setSetting('invitation_message', 'Un mensaje especial para el equipo.');

    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'nueva@levado.test',
        'role' => TenantUserRole::Viewer->value,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);
    $invitation->load('tenant');

    Mail::to($invitation->email)->send(new TeamInvitation($invitation));

    Mail::assertSent(TeamInvitation::class, function (TeamInvitation $mail) {
        return str_contains($mail->render(), 'Un mensaje especial para el equipo.');
    });
});

test('email de invitación sin mensaje personalizado no muestra el bloque de custom message', function () {
    Mail::fake();

    [$user, $tenant] = ownerForInvitationMessage();

    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => 'nueva2@levado.test',
        'role' => TenantUserRole::Viewer->value,
        'token' => Str::random(64),
        'expires_at' => now()->addDays(7),
    ]);
    $invitation->load('tenant');

    Mail::to($invitation->email)->send(new TeamInvitation($invitation));

    Mail::assertSent(TeamInvitation::class, function (TeamInvitation $mail) {
        return ! str_contains($mail->render(), 'border-left: 3px solid #C8622A');
    });
});

test('mensaje de más de 1000 caracteres es rechazado', function () {
    [$user, $tenant] = ownerForInvitationMessage();

    $this->actingAs($user)
        ->patch(route('business.update'), [
            'name' => $tenant->name,
            'country' => $tenant->country ?? 'AR',
            'currency' => $tenant->currency ?? 'ARS',
            'productive_hours_month' => $tenant->productive_hours_month ?? 160,
            'invitation_message' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors('invitation_message');
});

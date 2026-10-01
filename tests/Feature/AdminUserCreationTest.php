<?php

use App\Enums\TenantUserRole;
use App\Mail\PasswordResetMail;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function adminUserCreationSuperAdmin(): User
{
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => Tenant::factory()->create()->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::SuperAdmin->value,
        'active' => true,
    ]);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function adminUserCreationPayload(Tenant $tenant, array $overrides = []): array
{
    return array_merge([
        'name' => 'Nuevo Usuario',
        'email' => 'nuevo@example.com',
        'tenant_id' => $tenant->id,
        'role' => TenantUserRole::Admin->value,
    ], $overrides);
}

test('crear usuario con contraseña no envía correo y puede ingresar', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();

    $this->actingAs(adminUserCreationSuperAdmin())
        ->post(route('admin.users.store'), adminUserCreationPayload($tenant, [
            'password' => 'Clave-segura-123',
            'password_confirmation' => 'Clave-segura-123',
        ]))
        ->assertRedirect()
        ->assertSessionHas('status');

    $user = User::where('email', 'nuevo@example.com')->firstOrFail();

    expect(Hash::check('Clave-segura-123', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(TenantUser::where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists())->toBeTrue();

    Mail::assertNothingSent();

    auth()->logout();

    $this->post(route('login'), ['email' => 'nuevo@example.com', 'password' => 'Clave-segura-123']);

    $this->assertAuthenticatedAs($user);
});

test('crear usuario sin contraseña envía el correo de recuperación', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();

    $this->actingAs(adminUserCreationSuperAdmin())
        ->post(route('admin.users.store'), adminUserCreationPayload($tenant))
        ->assertRedirect();

    expect(User::where('email', 'nuevo@example.com')->exists())->toBeTrue();

    Mail::assertSent(PasswordResetMail::class);
});

test('la confirmación de contraseña debe coincidir', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(adminUserCreationSuperAdmin())
        ->post(route('admin.users.store'), adminUserCreationPayload($tenant, [
            'password' => 'Clave-segura-123',
            'password_confirmation' => 'otra-cosa',
        ]))
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'nuevo@example.com')->exists())->toBeFalse();
});

test('usuario existente se asocia al comercio y conserva su contraseña', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $existing = User::factory()->create([
        'email' => 'nuevo@example.com',
        'password' => 'Clave-original-1',
    ]);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->post(route('admin.users.store'), adminUserCreationPayload($tenant, [
            'password' => 'Clave-segura-123',
            'password_confirmation' => 'Clave-segura-123',
        ]))
        ->assertRedirect();

    expect(Hash::check('Clave-original-1', $existing->fresh()->password))->toBeTrue()
        ->and(TenantUser::where('tenant_id', $tenant->id)->where('user_id', $existing->id)->exists())->toBeTrue();

    Mail::assertNothingSent();
});

test('un usuario que no es super admin no puede crear usuarios', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('admin.users.store'), adminUserCreationPayload($tenant))
        ->assertForbidden();

    expect(User::where('email', 'nuevo@example.com')->exists())->toBeFalse();
});

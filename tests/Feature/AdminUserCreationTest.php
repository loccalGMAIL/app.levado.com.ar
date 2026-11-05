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

test('editar usuario con nueva contraseña la cambia', function () {
    $user = User::factory()->create(['password' => 'Clave-original-1']);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.update', $user), [
            'name' => 'Nombre Editado',
            'email' => $user->email,
            'password' => 'Clave-nueva-456',
            'password_confirmation' => 'Clave-nueva-456',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $user->refresh();

    expect($user->name)->toBe('Nombre Editado')
        ->and(Hash::check('Clave-nueva-456', $user->password))->toBeTrue();
});

test('editar usuario sin contraseña conserva la actual', function () {
    $user = User::factory()->create(['password' => 'Clave-original-1']);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.update', $user), [
            'name' => 'Nombre Editado',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect();

    expect(Hash::check('Clave-original-1', $user->fresh()->password))->toBeTrue();
});

test('editar usuario con confirmación distinta falla en el bag updateUser', function () {
    $user = User::factory()->create(['password' => 'Clave-original-1']);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'Clave-nueva-456',
            'password_confirmation' => 'otra-cosa',
        ])
        ->assertSessionHasErrorsIn('updateUser', 'password');

    expect(Hash::check('Clave-original-1', $user->fresh()->password))->toBeTrue();
});

test('cambiar el comercio y rol de un usuario mueve su membresía', function () {
    $from = Tenant::factory()->create();
    $to = Tenant::factory()->create(['name' => 'Panadería Destino']);
    $user = User::factory()->create();
    $membership = TenantUser::create([
        'tenant_id' => $from->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
    ]);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.memberships.update', [$user, $membership]), [
            'tenant_id' => $to->id,
            'role' => TenantUserRole::Admin->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $membership->refresh();

    expect($membership->tenant_id)->toBe($to->id)
        ->and($membership->role)->toBe(TenantUserRole::Admin)
        ->and($user->tenantUsers()->count())->toBe(1);

    $this->assertDatabaseHas('admin_audit_logs', [
        'action' => 'user.membership_updated',
        'target_id' => $user->id,
        'tenant_id' => $to->id,
    ]);
});

test('no se puede mover un usuario a un comercio al que ya pertenece', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create();
    $membership = TenantUser::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'role' => TenantUserRole::Viewer->value]);
    TenantUser::create(['tenant_id' => $tenantB->id, 'user_id' => $user->id, 'role' => TenantUserRole::Viewer->value]);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.memberships.update', [$user, $membership]), [
            'tenant_id' => $tenantB->id,
            'role' => TenantUserRole::Viewer->value,
        ])
        ->assertSessionHasErrorsIn('updateMembership', 'tenant_id');

    expect($membership->fresh()->tenant_id)->toBe($tenantA->id);
});

test('la membresía debe pertenecer al usuario de la ruta', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherMembership = TenantUser::create([
        'tenant_id' => Tenant::factory()->create()->id,
        'user_id' => $other->id,
        'role' => TenantUserRole::Viewer->value,
    ]);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->patch(route('admin.users.memberships.update', [$user, $otherMembership]), [
            'tenant_id' => Tenant::factory()->create()->id,
            'role' => TenantUserRole::Viewer->value,
        ])
        ->assertNotFound();
});

test('un super admin no puede quitarse su propio rol de super admin', function () {
    $admin = adminUserCreationSuperAdmin();
    $membership = $admin->tenantUsers()->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.users.memberships.update', [$admin, $membership]), [
            'tenant_id' => $membership->tenant_id,
            'role' => TenantUserRole::Owner->value,
        ])
        ->assertSessionHas('error');

    expect($membership->fresh()->role)->toBe(TenantUserRole::SuperAdmin);
});

test('un usuario que no es super admin no puede cambiar membresías', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $membership = TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => TenantUserRole::Owner->value]);

    $this->actingAs($owner)
        ->patch(route('admin.users.memberships.update', [$owner, $membership]), [
            'tenant_id' => Tenant::factory()->create()->id,
            'role' => TenantUserRole::Owner->value,
        ])
        ->assertForbidden();

    expect($membership->fresh()->tenant_id)->toBe($tenant->id);
});

test('el listado de usuarios muestra el editor de comercios', function () {
    $user = User::factory()->create();
    TenantUser::create(['tenant_id' => Tenant::factory()->create()->id, 'user_id' => $user->id, 'role' => TenantUserRole::Viewer->value]);

    $this->actingAs(adminUserCreationSuperAdmin())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('memberships', false);
});

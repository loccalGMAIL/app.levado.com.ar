<?php

use App\Enums\TenantUserRole;
use App\Models\Invitation;
use App\Models\TenantUser;
use App\Models\User;

function teamMember($tenant, TenantUserRole $role): TenantUser
{
    return TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => User::factory()->create()->id,
        'role' => $role->value,
        'active' => true,
    ]);
}

test('nadie puede asignar el rol super_admin desde el equipo', function () {
    [$owner, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $member = teamMember($tenant, TenantUserRole::Viewer);

    $this->actingAs($owner)
        ->patch(route('team.members.role', $member), ['role' => 'super_admin'])
        ->assertSessionHasErrors('role');

    expect($member->fresh()->role)->toBe(TenantUserRole::Viewer)
        ->and($member->user->isSuperAdmin())->toBeFalse();
});

test('un admin no puede cambiarse el rol a sí mismo', function () {
    [$admin, $tenant] = tenantUserAs(TenantUserRole::Admin);
    $own = TenantUser::where('user_id', $admin->id)->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('team.members.role', $own), ['role' => 'owner'])
        ->assertSessionHas('error');

    expect($own->fresh()->role)->toBe(TenantUserRole::Admin);
});

test('un admin no puede degradar, desactivar ni nombrar propietarios', function () {
    [$admin, $tenant] = tenantUserAs(TenantUserRole::Admin);
    $owner = teamMember($tenant, TenantUserRole::Owner);
    $viewer = teamMember($tenant, TenantUserRole::Viewer);

    $this->actingAs($admin)->patch(route('team.members.role', $owner), ['role' => 'viewer'])->assertSessionHas('error');
    $this->actingAs($admin)->patch(route('team.members.deactivate', $owner))->assertSessionHas('error');
    $this->actingAs($admin)->patch(route('team.members.role', $viewer), ['role' => 'owner'])->assertSessionHas('error');

    expect($owner->fresh()->role)->toBe(TenantUserRole::Owner)
        ->and($owner->fresh()->active)->toBeTrue()
        ->and($viewer->fresh()->role)->toBe(TenantUserRole::Viewer);
});

test('un admin sí puede administrar a los demás miembros', function () {
    [$admin, $tenant] = tenantUserAs(TenantUserRole::Admin);
    $viewer = teamMember($tenant, TenantUserRole::Viewer);

    $this->actingAs($admin)->patch(route('team.members.role', $viewer), ['role' => 'admin'])->assertSessionHas('status');
    $this->actingAs($admin)->patch(route('team.members.deactivate', $viewer))->assertSessionHas('status');

    expect($viewer->fresh()->role)->toBe(TenantUserRole::Admin)
        ->and($viewer->fresh()->active)->toBeFalse();
});

test('el comercio no puede quedarse sin un propietario activo', function () {
    [$owner, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $otherOwner = teamMember($tenant, TenantUserRole::Owner);

    // Con dos propietarios, uno puede degradar al otro…
    $this->actingAs($owner)->patch(route('team.members.role', $otherOwner), ['role' => 'admin'])->assertSessionHas('status');
    expect($otherOwner->fresh()->role)->toBe(TenantUserRole::Admin);

    // …pero el último que queda no se puede tocar (tampoco por un super admin impersonando).
    [$superAdmin] = tenantUserAs(TenantUserRole::SuperAdmin);
    $lastOwner = TenantUser::where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($superAdmin)
        ->withSession(['impersonating_tenant_id' => $tenant->id])
        ->patch(route('team.members.deactivate', $lastOwner))
        ->assertSessionHas('error');

    expect($lastOwner->fresh()->active)->toBeTrue();
});

test('un usuario desactivado pierde la sesión abierta', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $user->update(['active' => false]);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('una invitación no reabre la sesión de un usuario desactivado', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $inactive = User::factory()->create(['active' => false]);
    $invitation = Invitation::create([
        'tenant_id' => $tenant->id,
        'email' => $inactive->email,
        'role' => TenantUserRole::Viewer->value,
        'token' => str_repeat('a', 64),
        'expires_at' => now()->addDay(),
    ]);

    $this->post(route('invitations.accept', $invitation->token))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(TenantUser::where('user_id', $inactive->id)->exists())->toBeFalse();
});

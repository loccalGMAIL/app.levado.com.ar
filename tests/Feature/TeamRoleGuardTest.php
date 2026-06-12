<?php

use App\Enums\TenantUserRole;
use App\Mail\TeamInvitation;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: User, 1: TenantUser}
 */
function memberOf(Tenant $tenant, TenantUserRole $role): array
{
    $user = User::factory()->create();
    $membership = TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $membership];
}

test('an admin cannot escalate a member to super_admin', function () {
    $tenant = Tenant::factory()->create();
    [$admin] = memberOf($tenant, TenantUserRole::Admin);
    [, $viewerMembership] = memberOf($tenant, TenantUserRole::Viewer);

    $this->actingAs($admin)
        ->patch(route('team.members.role', $viewerMembership), ['role' => 'super_admin'])
        ->assertSessionHasErrors('role');

    expect($viewerMembership->fresh()->role)->toBe(TenantUserRole::Viewer);
});

test('a user cannot change their own role', function () {
    $tenant = Tenant::factory()->create();
    [$admin, $adminMembership] = memberOf($tenant, TenantUserRole::Admin);

    $this->actingAs($admin)
        ->patch(route('team.members.role', $adminMembership), ['role' => 'owner'])
        ->assertForbidden();

    expect($adminMembership->fresh()->role)->toBe(TenantUserRole::Admin);
});

test('the sole owner cannot be demoted, but one of several can', function () {
    $tenant = Tenant::factory()->create();
    [, $soleOwnerMembership] = memberOf($tenant, TenantUserRole::Owner);
    [$admin] = memberOf($tenant, TenantUserRole::Admin);

    // Only one owner exists — demoting them is blocked.
    $this->actingAs($admin)
        ->patch(route('team.members.role', $soleOwnerMembership), ['role' => 'viewer'])
        ->assertRedirect()
        ->assertSessionHas('error');
    expect($soleOwnerMembership->fresh()->role)->toBe(TenantUserRole::Owner);

    // Add a second owner; now the first one can be demoted.
    [, $secondOwnerMembership] = memberOf($tenant, TenantUserRole::Owner);

    $this->actingAs($admin)
        ->patch(route('team.members.role', $secondOwnerMembership), ['role' => 'admin'])
        ->assertRedirect();
    expect($secondOwnerMembership->fresh()->role)->toBe(TenantUserRole::Admin);
});

test('the last active owner cannot be deactivated', function () {
    $tenant = Tenant::factory()->create();
    [, $ownerMembership] = memberOf($tenant, TenantUserRole::Owner);
    [$admin] = memberOf($tenant, TenantUserRole::Admin);

    $this->actingAs($admin)
        ->patch(route('team.members.deactivate', $ownerMembership))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($ownerMembership->fresh()->active)->toBeTrue();
});

test('an owner can invite a team member with a valid role', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    [$owner] = memberOf($tenant, TenantUserRole::Owner);

    $this->actingAs($owner)
        ->post(route('team.invitations.store'), [
            'email' => 'nuevo@levado.test',
            'role' => TenantUserRole::Admin->value,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(Invitation::where('email', 'nuevo@levado.test')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
    Mail::assertSent(TeamInvitation::class);
});

test('inviting with super_admin role is rejected', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    [$owner] = memberOf($tenant, TenantUserRole::Owner);

    $this->actingAs($owner)
        ->post(route('team.invitations.store'), [
            'email' => 'nuevo@levado.test',
            'role' => TenantUserRole::SuperAdmin->value,
        ])
        ->assertSessionHasErrors('role');

    Mail::assertNothingSent();
});

test('a user cannot deactivate themselves', function () {
    $tenant = Tenant::factory()->create();
    [$admin, $adminMembership] = memberOf($tenant, TenantUserRole::Admin);

    $this->actingAs($admin)
        ->patch(route('team.members.deactivate', $adminMembership))
        ->assertForbidden();

    expect($adminMembership->fresh()->active)->toBeTrue();
});

<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminRolePermissionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_it_lists_every_role_with_its_current_permissions_and_marks_owner_locked(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/role-permissions')
            ->assertOk();

        $roles = collect($response->json('data.roles'));
        $owner = $roles->firstWhere('role', 'owner');
        $manager = $roles->firstWhere('role', 'manager');

        $this->assertTrue($owner['locked']);
        $this->assertSame(['*'], $owner['permissions']);
        $this->assertFalse($manager['locked']);
        $this->assertContains('packages.send', $manager['permissions']);
        $this->assertNotEmpty($response->json('data.catalog'));
    }

    public function test_super_admin_can_update_a_roles_default_permissions(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/viewer', [
                'permissions' => ['dashboard.view', 'customers.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['dashboard.view', 'customers.view']);
    }

    public function test_the_owner_role_cannot_be_edited(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/owner', ['permissions' => ['dashboard.view']])
            ->assertStatus(422);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/not-a-real-role', ['permissions' => ['dashboard.view']])
            ->assertStatus(404);
    }

    public function test_an_invalid_permission_slug_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/viewer', ['permissions' => ['not.a.real.permission']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_a_non_super_admin_cannot_manage_role_permissions(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/admin/role-permissions')
            ->assertForbidden();
    }

    /**
     * The whole point of this feature: editing a role's defaults from the
     * admin panel takes effect on tenants that ALREADY exist, immediately
     * — no redeploy, no per-tenant action needed.
     */
    public function test_editing_a_roles_defaults_propagates_to_an_existing_tenants_role(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $managerRole = \Spatie\Permission\Models\Role::where(['name' => 'manager', 'tenant_id' => $tenant->id])->first();
        $this->assertTrue($managerRole->hasPermissionTo('packages.send'));

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/manager', [
                'permissions' => ['dashboard.view'],
            ])
            ->assertOk();

        $managerRole->refresh();
        $this->assertTrue($managerRole->hasPermissionTo('dashboard.view'));
        $this->assertFalse($managerRole->hasPermissionTo('packages.send'));
    }

    /**
     * A full sync means REMOVING a permission the admin unchecks, not just
     * adding new ones — distinct from the additive-only
     * SyncTenantRolePermissionsAction used for catalog-growth catch-up.
     */
    public function test_the_sync_removes_permissions_no_longer_in_the_updated_list(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/cashier', ['permissions' => ['dashboard.view']])
            ->assertOk();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $cashierRole = \Spatie\Permission\Models\Role::where(['name' => 'cashier', 'tenant_id' => $tenant->id])->first();

        $this->assertEquals(['dashboard.view'], $cashierRole->permissions->pluck('name')->all());
    }

    /**
     * A NEW tenant registered after the admin edit picks up the updated
     * defaults too — ProvisionTenantRolesAction reads the same live
     * source (RolePermissionDefaultsService), not the old config array.
     */
    public function test_a_newly_registered_tenant_gets_the_updated_defaults(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->putJson('/api/v1/admin/role-permissions/viewer', ['permissions' => ['dashboard.view']])
            ->assertOk();

        [$tenant] = $this->createTenantWithUser(TenantRole::Viewer);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $viewerRole = \Spatie\Permission\Models\Role::where(['name' => 'viewer', 'tenant_id' => $tenant->id])->first();

        $this->assertEquals(['dashboard.view'], $viewerRole->permissions->pluck('name')->all());
    }
}

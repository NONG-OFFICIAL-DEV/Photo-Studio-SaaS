<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminTenantRolePermissionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_it_lists_a_specific_tenants_roles_and_permissions(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions")
            ->assertOk();

        $roles = collect($response->json('data.roles'));
        $ownerRole = $roles->firstWhere('role', 'owner');
        $manager = $roles->firstWhere('role', 'manager');

        $this->assertTrue($ownerRole['locked']);
        $this->assertSame(['*'], $ownerRole['permissions']);
        $this->assertFalse($manager['locked']);
        $this->assertContains('packages.send', $manager['permissions']);
        $this->assertNotEmpty($response->json('data.catalog'));
    }

    public function test_super_admin_can_edit_one_tenants_role_permissions(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions/viewer", [
                'permissions' => ['dashboard.view'],
            ])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['dashboard.view']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $viewerRole = Role::where(['name' => 'viewer', 'tenant_id' => $tenant->id])->first();
        $this->assertEquals(['dashboard.view'], $viewerRole->permissions->pluck('name')->all());
    }

    /**
     * The whole point: editing tenant A's Viewer role must NOT affect
     * tenant B's Viewer role — this is a per-tenant override, not a
     * shared default.
     */
    public function test_editing_one_tenants_role_does_not_affect_another_tenant(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenantA->id}/role-permissions/viewer", [
                'permissions' => ['dashboard.view'],
            ])
            ->assertOk();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
        $viewerB = Role::where(['name' => 'viewer', 'tenant_id' => $tenantB->id])->first();

        $this->assertContains('customers.view', $viewerB->permissions->pluck('name')->all());
    }

    public function test_the_owner_role_cannot_be_edited(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions/owner", ['permissions' => ['dashboard.view']])
            ->assertStatus(422);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions/not-a-real-role", ['permissions' => ['dashboard.view']])
            ->assertStatus(404);
    }

    public function test_an_invalid_permission_slug_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions/viewer", ['permissions' => ['not.a.real.permission']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_a_non_super_admin_cannot_manage_tenant_role_permissions(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/role-permissions")
            ->assertForbidden();
    }
}

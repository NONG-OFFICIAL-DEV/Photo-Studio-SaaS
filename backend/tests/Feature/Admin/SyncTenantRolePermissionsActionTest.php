<?php

namespace Tests\Feature\Admin;

use App\Actions\SyncTenantRolePermissionsAction;
use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Covers the reconciliation semantics of SyncTenantRolePermissionsAction
 * (the engine behind `permissions:sync-tenants`): Owner must be fully
 * synced (add AND remove) to always equal the current catalog exactly,
 * while every other role stays additive-only so an admin's per-tenant or
 * global customization is never silently undone by a catch-up run.
 */
class SyncTenantRolePermissionsActionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_loses_a_permission_that_was_removed_from_the_catalog(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $stale = Permission::findOrCreate('stale.leftover', 'api');
        $owner = Role::where(['name' => TenantRole::Owner->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])->first();
        $owner->givePermissionTo($stale);

        $this->assertTrue($owner->fresh()->hasPermissionTo('stale.leftover'));

        app(SyncTenantRolePermissionsAction::class)->execute($tenant);

        $this->assertFalse($owner->fresh()->hasPermissionTo('stale.leftover'));
    }

    public function test_owner_still_gains_a_permission_newly_added_to_the_catalog(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $owner = Role::where(['name' => TenantRole::Owner->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])->first();

        // A permission already present in the current catalog (e.g. from another
        // module) but never granted to this tenant's Owner — simulates a tenant
        // that registered before that catalog entry existed.
        $owner->revokePermissionTo('reports.export');
        $this->assertFalse($owner->fresh()->hasPermissionTo('reports.export'));

        app(SyncTenantRolePermissionsAction::class)->execute($tenant);

        $this->assertTrue($owner->fresh()->hasPermissionTo('reports.export'));
    }

    public function test_a_non_owner_roles_manual_customization_is_not_reverted(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $viewer = Role::where(['name' => TenantRole::Viewer->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])->first();

        // Viewer doesn't have this by default — grant it as a one-off customization.
        $viewer->givePermissionTo('reports.export');
        $this->assertTrue($viewer->fresh()->hasPermissionTo('reports.export'));

        app(SyncTenantRolePermissionsAction::class)->execute($tenant);

        $this->assertTrue($viewer->fresh()->hasPermissionTo('reports.export'));
    }

    public function test_a_non_owner_role_still_gains_permissions_newly_added_to_its_defaults(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $manager = Role::where(['name' => TenantRole::Manager->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])->first();

        $manager->revokePermissionTo('reports.export');
        $this->assertFalse($manager->fresh()->hasPermissionTo('reports.export'));

        app(SyncTenantRolePermissionsAction::class)->execute($tenant);

        $this->assertTrue($manager->fresh()->hasPermissionTo('reports.export'));
    }
}

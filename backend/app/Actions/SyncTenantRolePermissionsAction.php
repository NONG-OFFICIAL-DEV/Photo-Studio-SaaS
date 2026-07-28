<?php

namespace App\Actions;

use App\Enums\TenantRole;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grants a tenant's existing roles any permissions in config/permissions.php
 * that weren't there yet (e.g. a new module shipped after the tenant was
 * registered). Purely additive — givePermissionTo() no-ops on permissions
 * already attached and never removes one, so a tenant's own customizations
 * to their roles are never touched.
 *
 * ProvisionTenantRolesAction (registration-time) and this action share the
 * same config-driven default matrix; this is what keeps that matrix "live"
 * for tenants that registered before a permission existed.
 */
class SyncTenantRolePermissionsAction
{
    public function execute(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $catalog = Arr::flatten(config('permissions.catalog'));
        $defaults = config('permissions.defaults');

        $allPermissions = collect($catalog)
            ->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'api')]);

        foreach (TenantRole::cases() as $tenantRole) {
            $role = Role::firstOrCreate([
                'name' => $tenantRole->value,
                'guard_name' => 'api',
                'tenant_id' => $tenant->id,
            ]);

            $grants = $defaults[$tenantRole->value] ?? [];

            $permissionModels = in_array('*', $grants, true)
                ? $allPermissions->values()
                : $allPermissions->only($grants)->values();

            $role->givePermissionTo($permissionModels);
        }
    }
}

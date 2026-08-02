<?php

namespace App\Actions;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Services\RolePermissionDefaultsService;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grants a tenant's existing roles any permissions in the (admin-editable,
 * see RolePermissionDefaultsService) default matrix that weren't there yet
 * — e.g. a brand-new module/permission slug shipped after the tenant was
 * registered. Additive only for non-Owner roles — givePermissionTo()
 * no-ops on permissions already attached and never removes one, so an
 * admin editing ONE role's defaults elsewhere (SyncRoleDefaultPermissionsAction,
 * which DOES fully resync add+remove) doesn't get its removals undone by a
 * later run of this command.
 *
 * Owner is the one exception: it always means "every catalog permission",
 * with no admin UI to manage it, so it's fully reconciled (add AND remove)
 * against the current catalog every run. Without this, a permission
 * dropped from the catalog (e.g. a module getting locked down) would stay
 * granted to every tenant's Owner forever, since nothing else ever
 * revisits it.
 *
 * ProvisionTenantRolesAction (registration-time) and this action share the
 * same live default matrix; this is what keeps that matrix applied to
 * tenants that registered before a permission existed.
 */
class SyncTenantRolePermissionsAction
{
    public function __construct(protected RolePermissionDefaultsService $defaults)
    {
    }

    public function execute(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $catalog = Arr::flatten(config('permissions.catalog'));

        $allPermissions = collect($catalog)
            ->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'api')]);

        foreach (TenantRole::cases() as $tenantRole) {
            $role = Role::firstOrCreate([
                'name' => $tenantRole->value,
                'guard_name' => 'api',
                'tenant_id' => $tenant->id,
            ]);

            $grants = $this->defaults->get($tenantRole);

            $permissionModels = in_array('*', $grants, true)
                ? $allPermissions->values()
                : $allPermissions->only($grants)->values();

            if ($tenantRole === TenantRole::Owner) {
                $role->syncPermissions($permissionModels);
            } else {
                $role->givePermissionTo($permissionModels);
            }
        }
    }
}

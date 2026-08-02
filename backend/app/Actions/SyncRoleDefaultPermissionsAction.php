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
 * Applies an admin-edited default permission set for ONE role to EVERY
 * existing tenant's copy of that role. Full syncPermissions() (add AND
 * remove) — not the additive-only SyncTenantRolePermissionsAction — since
 * there's no per-tenant customization to protect: the super admin's
 * default matrix is the single source of truth for every tenant's baseline
 * roles (see RolePermissionDefaultsService), so a permission the admin
 * unchecks here should actually disappear everywhere, not just stop being
 * re-granted.
 */
class SyncRoleDefaultPermissionsAction
{
    public function __construct(protected RolePermissionDefaultsService $defaults)
    {
    }

    public function execute(TenantRole $role, array $permissions): void
    {
        $this->defaults->set($role, $permissions);

        $catalog = Arr::flatten(config('permissions.catalog'));

        $allPermissions = collect($catalog)
            ->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'api')]);

        $permissionModels = in_array('*', $permissions, true)
            ? $allPermissions->values()
            : $allPermissions->only($permissions)->values();

        Tenant::withoutGlobalScopes()->get()->each(function (Tenant $tenant) use ($role, $permissionModels) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            Role::where([
                'name' => $role->value,
                'tenant_id' => $tenant->id,
                'guard_name' => 'api',
            ])->first()?->syncPermissions($permissionModels);
        });
    }
}

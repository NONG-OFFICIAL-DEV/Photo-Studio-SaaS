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
 * Creates the 7 baseline roles (Owner..Viewer) for a newly registered
 * tenant, each with its own copy scoped to that tenant (teams feature),
 * pre-populated from RolePermissionDefaultsService's live default matrix
 * (admin-editable — see AdminRolePermissionController).
 *
 * Idempotent: safe to re-run (e.g. after adding a new baseline role) —
 * existing roles are left untouched via firstOrCreate.
 */
class ProvisionTenantRolesAction
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

            $role->syncPermissions($permissionModels);
        }
    }
}

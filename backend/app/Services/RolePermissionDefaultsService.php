<?php

namespace App\Services;

use App\Enums\TenantRole;
use App\Models\RolePermissionDefault;

/**
 * Single source of truth for "which permissions does this baseline role
 * get by default" — read by ProvisionTenantRolesAction (new tenant),
 * SyncTenantRolePermissionsAction (catalog grew, catch existing tenants
 * up additively), and SyncRoleDefaultPermissionsAction (admin edited a
 * role's defaults, full resync across every tenant). Keeping it in one
 * place means those three call sites can never disagree about where a
 * role's permission list comes from.
 */
class RolePermissionDefaultsService
{
    /**
     * Owner is hardcoded to '*' (every permission) — deliberately not a
     * DB row, so it can never be edited down to less than full access,
     * even from the admin UI (see AdminRolePermissionController).
     */
    public function get(TenantRole $role): array
    {
        if ($role === TenantRole::Owner) {
            return ['*'];
        }

        $stored = RolePermissionDefault::where('role', $role->value)->value('permissions');

        return $stored ?? (config('permissions.defaults')[$role->value] ?? []);
    }

    public function set(TenantRole $role, array $permissions): RolePermissionDefault
    {
        return RolePermissionDefault::updateOrCreate(
            ['role' => $role->value],
            ['permissions' => $permissions]
        );
    }

    /**
     * @return array<string, array<int, string>> role value => permission list
     */
    public function all(): array
    {
        return collect(TenantRole::cases())
            ->mapWithKeys(fn (TenantRole $role) => [$role->value => $this->get($role)])
            ->all();
    }
}

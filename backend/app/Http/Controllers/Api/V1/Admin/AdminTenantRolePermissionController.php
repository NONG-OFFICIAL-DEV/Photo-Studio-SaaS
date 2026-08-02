<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lets a platform super admin view and edit ONE SPECIFIC tenant's own
 * Manager..Viewer role permissions — a per-tenant override on top of the
 * global default matrix (App\Http\Controllers\Api\V1\Admin\
 * AdminRolePermissionController). Every tenant's roles already live in
 * their own team-scoped rows (Spatie's team feature, keyed by tenant_id),
 * so editing here only ever touches that one tenant's copy and never the
 * shared role_permission_defaults table — a later global edit
 * (SyncRoleDefaultPermissionsAction) will still re-sync this tenant like
 * every other one, since there's no separate "has this tenant been
 * customized" flag; this is a deliberate one-off override, not a
 * permanently protected exception.
 *
 * Owner is deliberately excluded: always every permission, non-editable,
 * so a tenant can never end up with zero fully-privileged accounts.
 */
class AdminTenantRolePermissionController extends Controller
{
    use ApiResponse;

    public function index(Tenant $tenant): JsonResponse
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $this->success([
            'catalog' => config('permissions.catalog'),
            'roles' => collect(TenantRole::cases())->map(function (TenantRole $role) use ($tenant) {
                $locked = $role === TenantRole::Owner;

                $permissions = $locked
                    ? ['*']
                    : Role::where(['name' => $role->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])
                        ->first()?->permissions->pluck('name')->all() ?? [];

                return [
                    'role' => $role->value,
                    'label' => $role->label(),
                    'locked' => $locked,
                    'permissions' => $permissions,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, Tenant $tenant, string $role): JsonResponse
    {
        $tenantRole = TenantRole::tryFrom($role);

        abort_if(! $tenantRole, 404, 'Unknown role.');
        abort_if($tenantRole === TenantRole::Owner, 422, 'The Owner role always has full access and cannot be edited.');

        $validCatalog = Arr::flatten(config('permissions.catalog'));

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in($validCatalog)],
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $permissionModels = collect($validated['permissions'])
            ->map(fn (string $name) => Permission::findOrCreate($name, 'api'))
            ->values();

        Role::where(['name' => $tenantRole->value, 'tenant_id' => $tenant->id, 'guard_name' => 'api'])
            ->first()
            ?->syncPermissions($permissionModels);

        return $this->success([
            'role' => $tenantRole->value,
            'permissions' => $validated['permissions'],
        ], 'Role permissions updated.');
    }
}

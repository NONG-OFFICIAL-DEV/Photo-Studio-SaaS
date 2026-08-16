<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * role_permission_defaults is the LIVE source of truth for non-Owner
     * role grants (see RolePermissionDefaultsService) — it was seeded once
     * from config('permissions.defaults') by the table's creation
     * migration, so simply adding 'branches.*' to that config array has no
     * effect on any environment where this table already exists; the DB
     * rows must be updated directly, additively, same as
     * SyncTenantRolePermissionsAction does for per-tenant roles.
     */
    private const GRANTS = [
        'manager' => ['branches.view', 'branches.create', 'branches.update', 'branches.delete'],
        'photographer' => ['branches.view'],
        'receptionist' => ['branches.view'],
        'viewer' => ['branches.view'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $role => $newPermissions) {
            $row = DB::table('role_permission_defaults')->where('role', $role)->first();

            if (! $row) {
                continue;
            }

            $current = json_decode($row->permissions, true) ?? [];
            $merged = array_values(array_unique(array_merge($current, $newPermissions)));

            DB::table('role_permission_defaults')
                ->where('role', $role)
                ->update(['permissions' => json_encode($merged), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $role => $removedPermissions) {
            $row = DB::table('role_permission_defaults')->where('role', $role)->first();

            if (! $row) {
                continue;
            }

            $current = json_decode($row->permissions, true) ?? [];
            $remaining = array_values(array_diff($current, $removedPermissions));

            DB::table('role_permission_defaults')
                ->where('role', $role)
                ->update(['permissions' => json_encode($remaining), 'updated_at' => now()]);
        }
    }
};

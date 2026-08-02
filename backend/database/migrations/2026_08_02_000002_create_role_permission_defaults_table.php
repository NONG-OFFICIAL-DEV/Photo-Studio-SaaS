<?php

use App\Enums\TenantRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Makes the "which permissions does each baseline role get" matrix
     * admin-editable at runtime instead of only via a config file + code
     * deploy. Owner deliberately has NO row here — it always gets every
     * permission, hardcoded, non-editable (see RolePermissionDefaultsService)
     * so there's always at least one fully-privileged account per tenant
     * and an admin can't accidentally lock every account out of a feature.
     *
     * Seeded from config('permissions.defaults')'s CURRENT values so this
     * migration is a pure infrastructure change — no tenant's effective
     * permissions change the moment this runs. From here on, this table
     * (not the config array) is the live source of truth; see
     * RolePermissionDefaultsService.
     */
    public function up(): void
    {
        Schema::create('role_permission_defaults', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('role')->unique();
            $table->jsonb('permissions');
            $table->timestamps();
        });

        $defaults = config('permissions.defaults');

        foreach (TenantRole::cases() as $role) {
            if ($role === TenantRole::Owner) {
                continue;
            }

            DB::table('role_permission_defaults')->insert([
                'id' => (string) Str::uuid(),
                'role' => $role->value,
                'permissions' => json_encode($defaults[$role->value] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_defaults');
    }
};

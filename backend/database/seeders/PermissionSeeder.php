<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Seeds the platform-wide permission catalog (guard: api). These are
     * NOT tenant-scoped — they're the fixed capability list every tenant's
     * roles are built from. See config/permissions.php and
     * App\Actions\ProvisionTenantRolesAction.
     */
    public function run(): void
    {
        foreach (Arr::flatten(config('permissions.catalog')) as $name) {
            Permission::findOrCreate($name, 'api');
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Order matters: permissions and
     * plans must exist before any tenant registers (registration
     * provisions tenant roles against the permission catalog and looks up
     * the free_trial plan).
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}

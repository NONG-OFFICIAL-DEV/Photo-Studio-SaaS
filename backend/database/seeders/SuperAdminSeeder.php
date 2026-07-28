<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Bootstraps the first platform Super Admin (Super Admin Panel access,
     * tenant_id = null). Credentials configurable via env for real
     * deployments; sane local defaults otherwise.
     */
    public function run(): void
    {
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@platform.test')],
            [
                'tenant_id' => null,
                'name' => 'Platform Super Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'is_super_admin' => true,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}

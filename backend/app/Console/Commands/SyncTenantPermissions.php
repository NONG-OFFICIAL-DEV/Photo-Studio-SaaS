<?php

namespace App\Console\Commands;

use App\Actions\SyncTenantRolePermissionsAction;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncTenantPermissions extends Command
{
    protected $signature = 'permissions:sync-tenants';

    protected $description = 'Grant every tenant\'s baseline roles any permissions added to the catalog since they registered (additive only)';

    public function handle(SyncTenantRolePermissionsAction $action): int
    {
        $tenants = Tenant::withoutGlobalScopes()->get();

        $this->withProgressBar($tenants, function (Tenant $tenant) use ($action) {
            $action->execute($tenant);
        });

        $this->newLine(2);
        $this->info("Synced permissions for {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}

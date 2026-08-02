<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring {--days=3 : How many days before expiry to warn}';

    protected $description = 'Notify a tenant\'s Owner (and super admins) once per period when their Trial/Active subscription is about to lapse';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expiringSoon((int) $this->option('days'));

        $this->info("Notified {$count} subscription(s) about to expire.");

        return self::SUCCESS;
    }
}

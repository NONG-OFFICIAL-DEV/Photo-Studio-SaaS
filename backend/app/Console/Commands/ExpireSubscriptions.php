<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Transition every Trial/Active subscription past its period end into Expired (or Cancelled, if already scheduled to cancel), across all tenants';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();

        $this->info("Transitioned {$count} subscription(s).");

        return self::SUCCESS;
    }
}

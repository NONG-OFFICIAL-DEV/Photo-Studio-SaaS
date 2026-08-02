<?php

namespace App\Notifications\Concerns;

/**
 * Every billing/subscription notification goes to two different
 * audiences at once (the tenant's Owner and every super admin) and each
 * needs a different destination for the same event — shared here so it's
 * one place to fix if either frontend route ever changes.
 */
trait ResolvesBillingLink
{
    protected function billingLink(object $notifiable): array
    {
        return $notifiable->is_super_admin
            ? ['name' => 'admin-tenants']
            : ['name' => 'billing'];
    }
}

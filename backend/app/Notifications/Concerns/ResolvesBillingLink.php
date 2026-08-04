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

    /**
     * Same destination as billingLink(), but as an absolute URL — for
     * channels (mail, Telegram) that need a real clickable link rather
     * than a frontend-router route name.
     */
    protected function billingUrl(object $notifiable): string
    {
        $path = $notifiable->is_super_admin ? '/admin/tenants' : '/billing';

        return rtrim(config('app.frontend_url'), '/').$path;
    }
}

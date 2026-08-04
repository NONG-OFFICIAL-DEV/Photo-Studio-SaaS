<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\TelegramAdminChannel;

/**
 * Every Billing notification goes only to App\Models\User (a tenant's
 * Owner and/or every super admin — see SubscriptionService::notifyOwners()
 * and AuthService::register()), so this can rely on User::wantsChannel()
 * existing rather than defending against other notifiable types.
 *
 * "system" here means the in-app bell / Notifications page (database
 * channel) — kept as its own toggle name in the UI since "database" is an
 * implementation detail nobody outside this code needs to know about.
 */
trait NotifiesViaPreferredChannels
{
    protected function preferredChannels(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->wantsChannel('system')) {
            $channels[] = 'database';
        }

        if ($notifiable->wantsChannel('mail')) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsChannel('telegram')) {
            $channels[] = TelegramAdminChannel::class;
        }

        return $channels;
    }
}

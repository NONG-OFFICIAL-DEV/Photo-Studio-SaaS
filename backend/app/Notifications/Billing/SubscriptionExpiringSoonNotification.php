<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Notification;

/**
 * Fired once per period by the `subscriptions:notify-expiring` sweep (see
 * SubscriptionService::expiringSoon()) — never queued, since this project
 * has no queue worker running yet; every Billing notification stays
 * synchronous until that's true.
 *
 * `via()` returning just ['database'] is the extension point for the next
 * feature: add 'mail' and/or a custom Telegram channel here (plus a
 * matching toMail()/toTelegram()) once tenants can pick a delivery channel
 * — nothing else about this class needs to change.
 *
 * No English copy lives here — `toDatabase()` carries only structured data
 * (an `event` key + params), the same convention the rest of the API uses
 * (see ApiResponse::success()'s `code`/`params`). The frontend renders the
 * actual message from its own i18n templates keyed by `event`.
 */
class SubscriptionExpiringSoonNotification extends Notification
{
    use ResolvesBillingLink;

    public function __construct(protected Subscription $subscription, protected int $daysLeft)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $endsAt = $this->subscription->status->value === 'trial'
            ? $this->subscription->trial_ends_at
            : $this->subscription->current_period_ends_at;

        return [
            'event' => 'subscription.expiring_soon',
            'severity' => 'warning',
            'tenant_id' => $this->subscription->tenant_id,
            'tenant_name' => $this->subscription->tenant?->name,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'ends_at' => $endsAt?->toIso8601String(),
            'days_left' => $this->daysLeft,
            'link' => $this->billingLink($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

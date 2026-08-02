<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Notification;

/**
 * Fired by SubscriptionService::renew() — a receipt-style confirmation for
 * the tenant's Owner, sent regardless of whether the Owner or an admin
 * triggered the renewal. See SubscriptionExpiringSoonNotification for
 * shared conventions.
 */
class SubscriptionRenewedNotification extends Notification
{
    use ResolvesBillingLink;

    public function __construct(protected Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'subscription.renewed',
            'severity' => 'success',
            'tenant_id' => $this->subscription->tenant_id,
            'tenant_name' => $this->subscription->tenant?->name,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'amount' => $this->subscription->amount,
            'period_ends_at' => $this->subscription->current_period_ends_at?->toIso8601String(),
            'link' => $this->billingLink($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

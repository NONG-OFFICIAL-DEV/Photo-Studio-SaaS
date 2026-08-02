<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Notification;

/**
 * Fired by SubscriptionService::reactivate() (reverses an admin suspension).
 * See SubscriptionExpiringSoonNotification for shared conventions.
 */
class SubscriptionReactivatedNotification extends Notification
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
            'event' => 'subscription.reactivated',
            'severity' => 'success',
            'tenant_id' => $this->subscription->tenant_id,
            'tenant_name' => $this->subscription->tenant?->name,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'link' => $this->billingLink($notifiable),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

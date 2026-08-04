<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by the `subscriptions:expire` sweep (SubscriptionService::expireDue())
 * only when a subscription lapses into Expired — a subscription the tenant
 * deliberately scheduled to Cancel isn't a surprise, so that path doesn't
 * notify. See SubscriptionExpiringSoonNotification for the multi-channel
 * and i18n conventions shared by every Billing notification.
 */
class SubscriptionExpiredNotification extends Notification
{
    use NotifiesViaPreferredChannels, ResolvesBillingLink;

    public function __construct(protected Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'subscription.expired',
            'severity' => 'danger',
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

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';
        $plan = $this->subscription->plan?->name ?? 'their';

        return (new MailMessage)
            ->subject("Subscription expired — {$tenant}")
            ->greeting('Subscription expired')
            ->line("{$tenant}'s {$plan} plan has expired.")
            ->action('View Billing', $this->billingUrl($notifiable));
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';
        $plan = $this->subscription->plan?->name ?? 'their';

        return "❌ {$tenant}'s {$plan} plan has expired.";
    }
}

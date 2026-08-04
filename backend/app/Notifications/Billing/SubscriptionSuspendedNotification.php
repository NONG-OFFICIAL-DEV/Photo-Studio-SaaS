<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by SubscriptionService::suspend() — an admin-only action, so the
 * tenant's Owner is the only intended recipient (a super admin already
 * knows, since they're the one who just did it). See
 * SubscriptionExpiringSoonNotification for shared conventions.
 */
class SubscriptionSuspendedNotification extends Notification
{
    use NotifiesViaPreferredChannels, ResolvesBillingLink;

    public function __construct(protected Subscription $subscription, protected ?User $actor)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'subscription.suspended',
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

        return (new MailMessage)
            ->subject("Subscription suspended — {$tenant}")
            ->greeting('Subscription suspended')
            ->line("{$tenant}'s subscription has been suspended.")
            ->action('View Billing', $this->billingUrl($notifiable));
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';

        return "🚫 {$tenant}'s subscription has been suspended.";
    }
}

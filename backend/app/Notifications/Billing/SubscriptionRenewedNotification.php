<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired by SubscriptionService::renew() — a receipt-style confirmation for
 * the tenant's Owner, sent regardless of whether the Owner or an admin
 * triggered the renewal. See SubscriptionExpiringSoonNotification for
 * shared conventions.
 */
class SubscriptionRenewedNotification extends Notification
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

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';
        $plan = $this->subscription->plan?->name ?? 'their';
        $amount = $this->subscription->amount !== null ? number_format((float) $this->subscription->amount, 2) : null;

        return (new MailMessage)
            ->subject("Subscription renewed — {$tenant}")
            ->greeting('Subscription renewed')
            ->line("{$tenant}'s {$plan} plan was renewed".($amount ? " for \${$amount}." : '.'))
            ->action('View Billing', $this->billingUrl($notifiable));
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';
        $plan = $this->subscription->plan?->name ?? 'their';
        $amount = $this->subscription->amount !== null ? number_format((float) $this->subscription->amount, 2) : null;

        return "💳 {$tenant}'s {$plan} plan was renewed".($amount ? " (\${$amount})." : '.');
    }
}

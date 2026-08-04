<?php

namespace App\Notifications\Billing;

use App\Models\Subscription;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use App\Notifications\Concerns\ResolvesBillingLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Fired once per period by the `subscriptions:notify-expiring` sweep (see
 * SubscriptionService::expiringSoon()).
 *
 * `toDatabase()` carries only structured data (an `event` key + params),
 * the same convention the rest of the API uses (see ApiResponse::success()'s
 * `code`/`params`) — the frontend renders the actual in-app message from
 * its own i18n templates keyed by `event`. `toMail()`/`toTelegram()` below
 * are the only place real English copy for this event lives, since neither
 * of those channels has a frontend to render anything.
 */
class SubscriptionExpiringSoonNotification extends Notification
{
    use NotifiesViaPreferredChannels, ResolvesBillingLink;

    public function __construct(protected Subscription $subscription, protected int $daysLeft)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'subscription.expiring_soon',
            'severity' => 'warning',
            'tenant_id' => $this->subscription->tenant_id,
            'tenant_name' => $this->subscription->tenant?->name,
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan?->name,
            'ends_at' => $this->endsAt()?->toIso8601String(),
            'days_left' => $this->daysLeft,
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
            ->subject("{$tenant}'s plan expires in {$this->daysLeft} day(s)")
            ->greeting('Subscription expiring soon')
            ->line("{$tenant}'s {$plan} plan expires in {$this->daysLeft} day(s)".($this->endsAt() ? ' (on '.$this->endsAt()->toDateString().').' : '.'))
            ->action('View Billing', $this->billingUrl($notifiable));
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->subscription->tenant?->name ?? 'A tenant';
        $plan = $this->subscription->plan?->name ?? 'their';

        return "⚠️ {$tenant}'s {$plan} plan expires in {$this->daysLeft} day(s).";
    }

    protected function endsAt(): ?Carbon
    {
        return $this->subscription->status->value === 'trial'
            ? $this->subscription->trial_ends_at
            : $this->subscription->current_period_ends_at;
    }
}

<?php

namespace App\Notifications\Billing;

use App\Models\PaymentConfirmation;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once, when a tenant submits an "I've paid" claim after a manual
 * bank transfer (see PaymentConfirmationService::submit()) — super-admin-
 * only, mirroring NewTenantRegisteredNotification (there's no tenant-side
 * recipient; the tenant already knows they just submitted it). Worth
 * reaching every enabled channel since it's time-sensitive — the sooner
 * an admin sees it, the sooner a real tenant gets unblocked.
 */
class PaymentClaimedNotification extends Notification
{
    use NotifiesViaPreferredChannels;

    public function __construct(protected PaymentConfirmation $claim)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'payment.claimed',
            'severity' => 'info',
            'tenant_id' => $this->claim->tenant_id,
            'tenant_name' => $this->claim->tenant?->name,
            'claim_id' => $this->claim->id,
            // Named to match useNotificationDisplay()'s existing `amount`
            // interpolation key (see SubscriptionRenewedNotification) rather
            // than the claimed_amount column name — the frontend template
            // interpolation is keyed on this field name.
            'amount' => $this->claim->claimed_amount,
            'link' => ['name' => 'admin-payment-claims'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->claim->tenant?->name ?? 'A tenant';
        $amount = $this->claim->claimed_amount !== null ? number_format((float) $this->claim->claimed_amount, 2) : null;
        $url = rtrim(config('app.frontend_url'), '/').'/admin/payment-claims';

        return (new MailMessage)
            ->subject("Payment claim submitted — {$tenant}")
            ->greeting('New payment claim')
            ->line("{$tenant} says they've paid".($amount ? " \${$amount}" : '').' via bank transfer. Check your bank app and confirm or reject the claim.')
            ->action('Review Payment Claims', $url);
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->claim->tenant?->name ?? 'A tenant';
        $amount = $this->claim->claimed_amount !== null ? number_format((float) $this->claim->claimed_amount, 2) : null;

        return "💰 {$tenant} says they've paid".($amount ? " \${$amount}" : '').' — check your bank app and review the claim.';
    }
}

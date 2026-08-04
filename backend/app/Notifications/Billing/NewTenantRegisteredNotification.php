<?php

namespace App\Notifications\Billing;

use App\Models\Tenant;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once, at the end of AuthService::register() — super-admin-only
 * (there's no tenant-side recipient for this event). See
 * SubscriptionExpiringSoonNotification for shared conventions.
 */
class NewTenantRegisteredNotification extends Notification
{
    use NotifiesViaPreferredChannels;

    public function __construct(protected Tenant $tenant, protected ?string $planName)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'tenant.registered',
            'severity' => 'info',
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'plan_name' => $this->planName,
            'link' => ['name' => 'admin-tenants'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/admin/tenants';

        return (new MailMessage)
            ->subject("New tenant registered: {$this->tenant->name}")
            ->greeting('New signup')
            ->line("{$this->tenant->name} just registered".($this->planName ? " on the {$this->planName} plan." : '.'))
            ->action('View Tenants', $url);
    }

    public function toTelegram(object $notifiable): string
    {
        return "🆕 New tenant registered: {$this->tenant->name}".($this->planName ? " ({$this->planName} plan)" : '');
    }
}

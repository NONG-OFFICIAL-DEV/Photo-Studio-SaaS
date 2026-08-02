<?php

namespace App\Notifications\Billing;

use App\Models\Tenant;
use Illuminate\Notifications\Notification;

/**
 * Fired once, at the end of AuthService::register() — super-admin-only
 * (there's no tenant-side recipient for this event). See
 * SubscriptionExpiringSoonNotification for shared conventions.
 */
class NewTenantRegisteredNotification extends Notification
{
    public function __construct(protected Tenant $tenant, protected ?string $planName)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
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
}

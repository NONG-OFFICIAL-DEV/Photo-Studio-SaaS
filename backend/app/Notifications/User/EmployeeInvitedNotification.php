<?php

namespace App\Notifications\User;

use App\Models\Tenant;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired from UserController::store() when an owner creates an employee
 * without setting a password directly (invite-by-email mode). $token is a
 * real password-reset broker token (see Password::sendResetLink()'s
 * callback in the controller) — this reuses the app's existing
 * reset-password page/endpoint entirely, just with invite-flavored copy.
 */
class EmployeeInvitedNotification extends Notification
{
    use NotifiesViaPreferredChannels;

    public function __construct(protected Tenant $tenant, protected string $token) {}

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'employee.invited',
            'severity' => 'info',
            'tenant_name' => $this->tenant->name,
            'link' => ['name' => 'login'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject("You've been invited to join {$this->tenant->name}")
            ->greeting("You're invited!")
            ->line("You've been invited to join {$this->tenant->name} on Photo Studio SaaS.")
            ->line('Set your password to get started.')
            ->action('Set Your Password', $url);
    }
}

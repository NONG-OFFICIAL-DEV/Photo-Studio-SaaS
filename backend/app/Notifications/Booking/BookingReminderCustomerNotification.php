<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Notifications\Channels\TelegramTenantBotChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Customer-facing counterpart to UpcomingBookingNotification. Customer has
 * no in-app notification bell (no customer portal exists yet), so there's
 * no toDatabase() here — just mail and/or the tenant's own Telegram bot,
 * whichever the customer has available. No preference system either
 * (Customer doesn't have User::wantsChannel()) — send on every channel
 * they've actually got.
 */
class BookingReminderCustomerNotification extends Notification
{
    public function __construct(protected Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        if ($notifiable->telegram_chat_id) {
            $channels[] = TelegramTenantBotChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->booking->tenant?->name ?? 'your photographer';
        $when = $this->booking->starts_at?->format('l, F j, Y \a\t g:i A');

        return (new MailMessage)
            ->subject('Upcoming photo session reminder')
            ->greeting("Hi {$notifiable->name},")
            ->line("This is a reminder about your upcoming session with {$tenant}".($when ? " on {$when}" : '').'.')
            ->when($this->booking->location_address, fn ($mail) => $mail->line("Location: {$this->booking->location_address}"));
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->booking->tenant?->name ?? 'your photographer';
        $when = $this->booking->starts_at?->format('l, F j, Y \a\t g:i A');
        $location = $this->booking->location_address ? "\nLocation: {$this->booking->location_address}" : '';

        return "📸 Reminder: your session with {$tenant}".($when ? " is on {$when}" : ' is coming up').".{$location}";
    }
}

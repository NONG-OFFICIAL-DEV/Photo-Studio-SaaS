<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once per booking by the `bookings:send-reminders` sweep (see
 * BookingReminderService::sendUpcomingReminders()) — to the photographer
 * assigned to the booking, not the tenant Owner, since they're the one
 * who actually needs to show up.
 */
class UpcomingBookingNotification extends Notification
{
    use NotifiesViaPreferredChannels;

    public function __construct(protected Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'booking.upcoming',
            'severity' => 'info',
            'booking_id' => $this->booking->id,
            'customer_name' => $this->booking->customer?->name,
            'date' => $this->booking->starts_at?->format('M j, Y g:i A'),
            'link' => ['name' => 'bookings-calendar'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->booking->customer?->name ?? 'A customer';
        $when = $this->booking->starts_at?->format('l, F j, Y \a\t g:i A');
        $url = rtrim(config('app.frontend_url'), '/').'/bookings/calendar';

        return (new MailMessage)
            ->subject("Upcoming booking with {$customer}")
            ->greeting('Booking reminder')
            ->line("You have a booking with {$customer}".($when ? " on {$when}" : '').'.')
            ->action('View Calendar', $url);
    }

    public function toTelegram(object $notifiable): string
    {
        $customer = $this->booking->customer?->name ?? 'A customer';
        $when = $this->booking->starts_at?->format('l, F j, Y \a\t g:i A');

        return "📅 Upcoming booking with {$customer}".($when ? " on {$when}" : '').'.';
    }
}

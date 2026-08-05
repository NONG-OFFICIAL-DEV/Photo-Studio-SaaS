<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\Booking\BookingReminderCustomerNotification;
use App\Notifications\Booking\UpcomingBookingNotification;
use Illuminate\Support\Facades\Notification;

class BookingReminderService
{
    /**
     * Reminds the assigned photographer and the customer once per booking,
     * for every Confirmed booking starting within the next $hoursAhead
     * hours. Run tenant-agnostically (no TenantContext set) so it sweeps
     * every tenant in one pass — intended for the daily scheduler.
     * reminder_sent_at guards against re-notifying on the next run.
     *
     * A tenant that has turned this off (Settings > Reminders) is skipped
     * entirely — its bookings are left with reminder_sent_at still null,
     * so turning the setting back on later still catches anything still
     * inside the window.
     */
    public function sendUpcomingReminders(int $hoursAhead = 24): int
    {
        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours($hoursAhead)])
            ->with(['customer', 'assignedUser', 'tenant'])
            ->get()
            ->filter(fn (Booking $booking) => $booking->tenant?->setting('booking_reminders_enabled', true));

        foreach ($bookings as $booking) {
            if ($booking->assignedUser) {
                Notification::send($booking->assignedUser, new UpcomingBookingNotification($booking));
            }

            if ($booking->customer) {
                Notification::send($booking->customer, new BookingReminderCustomerNotification($booking));
            }

            $booking->update(['reminder_sent_at' => now()]);
        }

        return $bookings->count();
    }
}

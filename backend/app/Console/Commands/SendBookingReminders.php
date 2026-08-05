<?php

namespace App\Console\Commands;

use App\Services\BookingReminderService;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--hours=24 : How many hours before the booking to remind}';

    protected $description = 'Remind the assigned photographer and the customer once per booking, for every Confirmed booking starting soon';

    public function handle(BookingReminderService $reminders): int
    {
        $count = $reminders->sendUpcomingReminders((int) $this->option('hours'));

        $this->info("Sent reminders for {$count} upcoming booking(s).");

        return self::SUCCESS;
    }
}

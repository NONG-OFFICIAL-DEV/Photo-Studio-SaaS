<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:mark-overdue')->dailyAt('00:30');
Schedule::command('subscriptions:expire')->daily();
Schedule::command('subscriptions:notify-expiring')->daily();
Schedule::command('bookings:send-reminders')->daily();
// Runs after invoices:mark-overdue so a freshly-overdue invoice gets its
// reminder the same day, not a full cycle later.
Schedule::command('invoices:send-payment-reminders')->dailyAt('01:00');

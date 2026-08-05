<?php

namespace App\Console\Commands;

use App\Services\InvoiceReminderService;
use Illuminate\Console\Command;

class SendInvoicePaymentReminders extends Command
{
    protected $signature = 'invoices:send-payment-reminders {--days=3 : How many days before the due date to send a "due soon" reminder}';

    protected $description = 'Remind the tenant\'s Owner(s) and the customer once per invoice, for invoices due soon or already overdue';

    public function handle(InvoiceReminderService $reminders): int
    {
        $dueSoon = $reminders->sendDueSoonReminders((int) $this->option('days'));
        $overdue = $reminders->sendOverdueReminders();

        $this->info("Sent {$dueSoon} due-soon reminder(s) and {$overdue} overdue reminder(s).");

        return self::SUCCESS;
    }
}

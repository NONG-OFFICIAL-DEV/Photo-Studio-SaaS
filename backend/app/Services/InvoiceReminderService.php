<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\TenantRole;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\Invoice\InvoiceDueSoonCustomerNotification;
use App\Notifications\Invoice\InvoiceDueSoonNotification;
use App\Notifications\Invoice\InvoiceOverdueCustomerNotification;
use App\Notifications\Invoice\InvoiceOverdueNotification;
use Illuminate\Notifications\Notification as NotificationClass;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payment reminders for the tenant's Owner(s) and the customer, run by
 * the daily `invoices:send-payment-reminders` sweep. Each reminder fires
 * once per invoice (guarded by its own *_reminder_sent_at timestamp) —
 * not a recurring nag every day it stays unpaid. A tenant that has
 * turned this off (Settings > Reminders) is skipped entirely.
 */
class InvoiceReminderService
{
    public function sendDueSoonReminders(int $daysAhead = 3): int
    {
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->whereNull('due_soon_reminder_sent_at')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString()])
            ->with(['customer', 'tenant'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->tenant?->setting('invoice_reminders_enabled', true));

        foreach ($invoices as $invoice) {
            $this->notifyOwners($invoice, new InvoiceDueSoonNotification($invoice));

            if ($invoice->customer) {
                Notification::send($invoice->customer, new InvoiceDueSoonCustomerNotification($invoice));
            }

            $invoice->update(['due_soon_reminder_sent_at' => now()]);
        }

        return $invoices->count();
    }

    public function sendOverdueReminders(): int
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->whereNull('overdue_reminder_sent_at')
            ->with(['customer', 'tenant'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->tenant?->setting('invoice_reminders_enabled', true));

        foreach ($invoices as $invoice) {
            $this->notifyOwners($invoice, new InvoiceOverdueNotification($invoice));

            if ($invoice->customer) {
                Notification::send($invoice->customer, new InvoiceOverdueCustomerNotification($invoice));
            }

            $invoice->update(['overdue_reminder_sent_at' => now()]);
        }

        return $invoices->count();
    }

    protected function notifyOwners(Invoice $invoice, NotificationClass $notification): void
    {
        $recipients = $this->ownersOf($invoice->tenant_id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    protected function ownersOf(string $tenantId): Collection
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($query) => $query->where('name', TenantRole::Owner->value))
            ->get();
    }
}

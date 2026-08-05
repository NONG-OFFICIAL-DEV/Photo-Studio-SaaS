<?php

namespace App\Notifications\Invoice;

use App\Models\Invoice;
use App\Notifications\Concerns\NotifiesViaPreferredChannels;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired once per invoice by the `invoices:send-payment-reminders` sweep,
 * the first time it's found in the Overdue status (see
 * InvoiceReminderService::sendOverdueReminders()) — to the tenant's
 * Owner(s).
 */
class InvoiceOverdueNotification extends Notification
{
    use NotifiesViaPreferredChannels;

    public function __construct(protected Invoice $invoice)
    {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'invoice.overdue',
            'severity' => 'danger',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'customer_name' => $this->invoice->customer?->name,
            'balance' => $this->invoice->balance_due,
            'link' => ['name' => 'invoices'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->invoice->customer?->name ?? 'A customer';
        $balance = number_format($this->invoice->balance_due, 2);
        $url = rtrim(config('app.frontend_url'), '/').'/invoices';

        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} is overdue")
            ->greeting('Invoice overdue')
            ->line("Invoice {$this->invoice->invoice_number} for {$customer} (\${$balance}) is now overdue.")
            ->action('View Invoices', $url);
    }

    public function toTelegram(object $notifiable): string
    {
        $customer = $this->invoice->customer?->name ?? 'A customer';
        $balance = number_format($this->invoice->balance_due, 2);

        return "🔴 Invoice {$this->invoice->invoice_number} for {$customer} (\${$balance}) is now overdue.";
    }
}

<?php

namespace App\Notifications\Invoice;

use App\Models\Invoice;
use App\Notifications\Channels\TelegramTenantBotChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Customer-facing counterpart to InvoiceDueSoonNotification. See
 * BookingReminderCustomerNotification for why there's no toDatabase()
 * and no preference system here.
 */
class InvoiceDueSoonCustomerNotification extends Notification
{
    public function __construct(protected Invoice $invoice)
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
        $tenant = $this->invoice->tenant?->name ?? 'your studio';
        $balance = number_format($this->invoice->balance_due, 2);

        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} is due soon")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your invoice {$this->invoice->invoice_number} from {$tenant} for \${$balance} is due on {$this->invoice->due_date?->toDateString()}.")
            ->line('Please arrange payment before the due date to avoid it becoming overdue.');
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->invoice->tenant?->name ?? 'your studio';
        $balance = number_format($this->invoice->balance_due, 2);

        return "🧾 Your invoice {$this->invoice->invoice_number} from {$tenant} for \${$balance} is due on {$this->invoice->due_date?->toDateString()}.";
    }
}

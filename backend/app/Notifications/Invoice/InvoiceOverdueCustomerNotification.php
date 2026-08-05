<?php

namespace App\Notifications\Invoice;

use App\Models\Invoice;
use App\Notifications\Channels\TelegramTenantBotChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Customer-facing counterpart to InvoiceOverdueNotification. See
 * BookingReminderCustomerNotification for why there's no toDatabase()
 * and no preference system here.
 */
class InvoiceOverdueCustomerNotification extends Notification
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
            ->subject("Invoice {$this->invoice->invoice_number} is overdue")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your invoice {$this->invoice->invoice_number} from {$tenant} for \${$balance} is now overdue.")
            ->line('Please arrange payment as soon as possible.');
    }

    public function toTelegram(object $notifiable): string
    {
        $tenant = $this->invoice->tenant?->name ?? 'your studio';
        $balance = number_format($this->invoice->balance_due, 2);

        return "🔴 Your invoice {$this->invoice->invoice_number} from {$tenant} for \${$balance} is now overdue. Please arrange payment as soon as possible.";
    }
}

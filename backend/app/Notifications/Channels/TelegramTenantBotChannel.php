<?php

namespace App\Notifications\Channels;

use App\Services\TelegramService;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification to a Customer's Telegram chat via their own
 * TENANT's bot (Customer::tenant->telegram_bot_token) — never the
 * platform-wide admin bot TelegramAdminChannel uses. Customers only ever
 * link Telegram through their studio's own bot (see
 * CustomerTelegramController::link()), so that's the only bot they've
 * actually started a chat with.
 */
class TelegramTenantBotChannel
{
    public function __construct(protected TelegramService $telegram)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $token = $notifiable->tenant?->telegram_bot_token;
        $chatId = $notifiable->telegram_chat_id;

        if (! $token || ! $chatId || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $this->telegram->sendMessage($token, $chatId, $notification->toTelegram($notifiable));
    }
}

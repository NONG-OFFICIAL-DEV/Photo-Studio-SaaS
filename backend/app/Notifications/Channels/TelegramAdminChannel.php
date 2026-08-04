<?php

namespace App\Notifications\Channels;

use App\Services\TelegramService;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification to a user's personal Telegram chat via the
 * platform-wide admin bot (config('services.platform_telegram') —
 * NOT any tenant's own customer-facing bot). Only reachable at all if
 * via() included this class, which itself already checked
 * User::wantsChannel('telegram') (preference on AND a linked chat_id) —
 * so the only remaining guard here is the bot actually being configured.
 */
class TelegramAdminChannel
{
    public function __construct(protected TelegramService $telegram)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $token = config('services.platform_telegram.bot_token');
        $chatId = $notifiable->telegram_chat_id;

        if (! $token || ! $chatId || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $this->telegram->sendMessage($token, $chatId, $notification->toTelegram($notifiable));
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram calls this for the single platform-wide admin bot (see
 * config('services.platform_telegram')) — completely separate from
 * TelegramWebhookController, which handles each TENANT's own bot.
 * No auth:api applies (Telegram has no account here); authenticity is
 * verified via the secret_token this bot registered with setWebhook,
 * sent back on every call as the X-Telegram-Bot-Api-Secret-Token header.
 */
class PlatformTelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): JsonResponse
    {
        $secret = config('services.platform_telegram.webhook_secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => true]);
        }

        $text = (string) $request->input('message.text', '');

        if (str_starts_with($text, '/start ')) {
            $token = trim(substr($text, 7));
            $chatId = $request->input('message.chat.id');

            $user = User::withoutGlobalScopes()
                ->where('telegram_link_token', $token)
                ->whereNull('telegram_chat_id')
                ->first();

            if ($user && $chatId) {
                $channels = $user->notificationChannelPreferences();
                $channels['telegram'] = true;

                $user->update([
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_linked_at' => now(),
                    'notification_channels' => $channels,
                ]);

                $telegram->sendMessage(
                    config('services.platform_telegram.bot_token'),
                    (string) $chatId,
                    "You're connected! You'll now receive platform notifications here."
                );
            }
        }

        // Telegram expects a fast 200 regardless of outcome, or it retries
        // the same update repeatedly.
        return response()->json(['ok' => true]);
    }
}

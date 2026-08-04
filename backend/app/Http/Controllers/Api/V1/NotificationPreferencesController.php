<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lets any authenticated user (tenant or super admin — same reachability
 * as NotificationController, see its docblock) choose which channels
 * deliver their Billing notifications: the in-app bell ("system"), email,
 * and/or their own linked Telegram chat via the platform bot (a
 * completely separate bot from any tenant's customer-facing one — see
 * config('services.platform_telegram')).
 */
class NotificationPreferencesController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'channels' => $user->notificationChannelPreferences(),
            'telegram' => [
                'linked' => $user->hasTelegramLinked(),
                'available' => $this->platformBotConfigured(),
            ],
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $request->user()->update([
            'notification_channels' => $request->only(['mail', 'system', 'telegram']),
        ]);

        return $this->success(null, 'Notification preferences updated.', code: 'NOTIFICATION_PREFERENCES_UPDATED');
    }

    /**
     * Mirrors CustomerTelegramController::link() — generates (or reuses) a
     * pending link token and returns the deep link to open in Telegram;
     * PlatformTelegramWebhookController exchanges it for a real chat_id
     * once the user taps Start.
     */
    public function linkTelegram(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->platformBotConfigured()) {
            return $this->error('The platform Telegram bot is not configured yet.', 422, [], 'PLATFORM_TELEGRAM_NOT_CONFIGURED');
        }

        if ($user->hasTelegramLinked()) {
            return $this->success(['linked' => true]);
        }

        if (! $user->telegram_link_token) {
            $user->update(['telegram_link_token' => Str::random(32)]);
        }

        $botUsername = config('services.platform_telegram.bot_username');

        return $this->success([
            'linked' => false,
            'link' => "https://t.me/{$botUsername}?start={$user->telegram_link_token}",
        ]);
    }

    public function unlinkTelegram(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = $user->notificationChannelPreferences();
        $channels['telegram'] = false;

        $user->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
            'telegram_linked_at' => null,
            'notification_channels' => $channels,
        ]);

        return $this->success(null, 'Telegram disconnected.', code: 'TELEGRAM_DISCONNECTED');
    }

    protected function platformBotConfigured(): bool
    {
        return (bool) config('services.platform_telegram.bot_token') && (bool) config('services.platform_telegram.bot_username');
    }
}

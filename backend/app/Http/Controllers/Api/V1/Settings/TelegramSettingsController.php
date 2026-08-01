<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConnectTelegramBotRequest;
use App\Services\TelegramService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class TelegramSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(protected TelegramService $telegram)
    {
    }

    /**
     * Validates the token against Telegram's own getMe endpoint (the only
     * way to know it's real), then registers a webhook so the bot can tell
     * us when a customer taps "start" on their deep link (see
     * TelegramWebhookController). Requires a public HTTPS URL reachable by
     * Telegram's servers — will fail in a local/dev environment with no
     * public domain, same as any other webhook-based integration.
     */
    public function connect(ConnectTelegramBotRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $token = $request->validated()['bot_token'];

        $me = $this->telegram->getMe($token);

        if (! ($me['ok'] ?? false)) {
            return $this->error('That bot token was rejected by Telegram — double-check it from @BotFather.', 422, [], 'TELEGRAM_INVALID_TOKEN');
        }

        $secret = Str::random(48);
        $webhookUrl = URL::route('api.v1.webhooks.telegram', ['tenant' => $tenant->id]);

        $hook = $this->telegram->setWebhook($token, $webhookUrl, $secret);

        if (! ($hook['ok'] ?? false)) {
            return $this->error('The bot token works, but Telegram could not reach this server to register the webhook. Make sure this app is deployed behind a public HTTPS domain, then try again.', 502, [], 'TELEGRAM_WEBHOOK_FAILED');
        }

        $tenant->update([
            'telegram_bot_token' => $token,
            'telegram_bot_username' => $me['result']['username'] ?? null,
            'telegram_webhook_secret' => $secret,
            'telegram_connected_at' => now(),
        ]);

        return $this->success([
            'connected' => true,
            'bot_username' => $tenant->telegram_bot_username,
        ], 'Telegram bot connected.');
    }

    public function disconnect(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tenant.settings.manage'), 403);

        $tenant = $request->user()->tenant;

        if ($tenant->telegram_bot_token) {
            $this->telegram->deleteWebhook($tenant->telegram_bot_token);
        }

        $tenant->update([
            'telegram_bot_token' => null,
            'telegram_bot_username' => null,
            'telegram_webhook_secret' => null,
            'telegram_connected_at' => null,
        ]);

        return $this->success(['connected' => false], 'Telegram bot disconnected.');
    }
}

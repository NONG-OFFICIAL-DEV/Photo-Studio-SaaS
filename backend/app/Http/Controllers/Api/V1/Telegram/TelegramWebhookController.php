<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram calls this — no auth:api/tenant middleware applies (Telegram has
 * no account here), so the tenant is resolved directly from the {tenant}
 * URL segment (with global scopes bypassed, same as InvoiceController's
 * publicPdf()) and authenticity is verified via the secret_token this
 * tenant's bot registered with setWebhook, sent back on every call as the
 * X-Telegram-Bot-Api-Secret-Token header — Laravel's `signed` middleware
 * doesn't apply here since Telegram can't produce that signature.
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $tenant, TelegramService $telegram): JsonResponse
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenant);

        if (! $tenant || ! $tenant->telegram_webhook_secret) {
            return response()->json(['ok' => true]);
        }

        if (! hash_equals($tenant->telegram_webhook_secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return response()->json(['ok' => true]);
        }

        $text = (string) $request->input('message.text', '');

        if (str_starts_with($text, '/start ')) {
            $token = trim(substr($text, 7));
            $chatId = $request->input('message.chat.id');

            $customer = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('telegram_link_token', $token)
                ->whereNull('telegram_chat_id')
                ->first();

            if ($customer && $chatId) {
                $customer->update([
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_linked_at' => now(),
                ]);

                $telegram->sendMessage(
                    $tenant->telegram_bot_token,
                    (string) $chatId,
                    "You're connected! {$tenant->name} can now send you photos and invoices here."
                );
            }
        }

        // Telegram expects a fast 200 regardless of outcome, or it retries
        // the same update repeatedly.
        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SendTelegramFilesRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\TelegramMessageLogResource;
use App\Models\Customer;
use App\Services\TelegramMessageLogService;
use App\Services\TelegramService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerTelegramController extends Controller
{
    use ApiResponse;

    public function __construct(protected TelegramService $telegram, protected TelegramMessageLogService $logs)
    {
    }

    public function activity(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $paginator = $this->logs->forCustomer($customer->id, $request->only(['type', 'status', 'perPage']));

        return $this->success(
            TelegramMessageLogResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Returns the deep link staff share with the customer once (in person,
     * SMS, an existing chat — any channel) so they can tap "start" and hand
     * us their chat_id; bots can't message a stranger first, so this
     * handshake is unavoidable. Reuses the existing token if one was
     * already issued and the customer still hasn't tapped it, rather than
     * invalidating an unclicked link every time this is opened again.
     */
    public function link(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $tenant = $request->user()->tenant;

        if (! $tenant->telegramConnected()) {
            return $this->error('Connect a Telegram bot in Settings first.', 422, [], 'TELEGRAM_NOT_CONFIGURED');
        }

        if ($customer->telegram_chat_id) {
            return $this->success(['linked' => true]);
        }

        if (! $customer->telegram_link_token) {
            $customer->update(['telegram_link_token' => Str::random(32)]);
        }

        return $this->success([
            'linked' => false,
            'link' => "https://t.me/{$tenant->telegram_bot_username}?start={$customer->telegram_link_token}",
        ]);
    }

    public function unlink(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
            'telegram_linked_at' => null,
        ]);

        return $this->success(new CustomerResource($customer), 'Telegram disconnected for this customer.');
    }

    /**
     * The "click one button, customer gets the photos" flow. Uploaded
     * files are streamed straight through to Telegram's servers and
     * discarded when the request ends — never written to this app's own
     * storage disk.
     */
    public function sendFiles(SendTelegramFilesRequest $request, Customer $customer): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant->telegramConnected()) {
            return $this->error('Connect a Telegram bot in Settings first.', 422, [], 'TELEGRAM_NOT_CONFIGURED');
        }

        if (! $customer->telegram_chat_id) {
            return $this->error('This customer has not connected Telegram yet.', 422, [], 'TELEGRAM_CUSTOMER_NOT_LINKED');
        }

        $sent = 0;
        $failed = [];
        $caption = $request->string('caption')->toString() ?: null;

        foreach ($request->file('files') as $file) {
            $result = $this->telegram->sendDocument(
                $tenant->telegram_bot_token,
                $customer->telegram_chat_id,
                fopen($file->getRealPath(), 'r'),
                $file->getClientOriginalName(),
                $caption
            );

            if ($result['ok'] ?? false) {
                $sent++;
            } else {
                $failed[] = $file->getClientOriginalName();
            }
        }

        $totalFiles = count($request->file('files'));

        if ($sent === 0) {
            $this->logs->record($customer, 'album', "{$totalFiles} file(s)", null, false, 'Telegram rejected every file — check the bot is still connected.', $request->user());

            return $this->error('Telegram rejected every file — check the bot is still connected.', 502, [], 'TELEGRAM_SEND_FAILED');
        }

        $this->logs->record(
            $customer,
            'album',
            "{$totalFiles} file(s)",
            null,
            true,
            $failed ? 'Sent '.$sent." of {$totalFiles}; failed: ".implode(', ', $failed) : null,
            $request->user()
        );

        return $this->success(
            ['sent' => $sent, 'failed' => $failed],
            $failed ? "Sent {$sent} file(s), ".count($failed).' failed.' : "Sent {$sent} file(s) via Telegram."
        );
    }
}

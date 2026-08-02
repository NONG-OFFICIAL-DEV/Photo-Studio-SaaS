<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Http\Controllers\Controller;
use App\Http\Resources\TelegramMessageLogResource;
use App\Services\TelegramMessageLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-wide "did our Telegram sends actually go through" history —
 * reusing customers.view rather than a new permission slug, since this
 * is fundamentally customer-communication history (same audience as
 * Customer Notes), not a distinct capability of its own.
 */
class TelegramActivityController extends Controller
{
    use ApiResponse;

    public function __construct(protected TelegramMessageLogService $logs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('customers.view'), 403);

        $paginator = $this->logs->paginate($request->only([
            'customer_id', 'type', 'status', 'date_from', 'date_to', 'perPage',
        ]));

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
}

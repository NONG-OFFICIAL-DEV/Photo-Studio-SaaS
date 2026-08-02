<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Purely user-scoped (via Notifiable on User) — deliberately reachable
 * with nothing but auth:api (see routes/api/v1.php), no `tenant` or
 * `subscription.active` middleware, so a tenant Owner whose subscription
 * just lapsed can still see (and understand) the notification that says
 * so, and a super admin (no tenant at all) can use the same endpoints.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $paginator = $request->user()->notifications()
            ->paginate($request->integer('perPage', 20));

        return $this->success(
            NotificationResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ]
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success(null, 'Notification marked as read.', code: 'NOTIFICATION_MARKED_READ');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(null, 'All notifications marked as read.', code: 'ALL_NOTIFICATIONS_MARKED_READ');
    }
}

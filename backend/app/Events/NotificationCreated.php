<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast counterpart to a notification that just landed on the
 * `database` channel — see AppServiceProvider::configureNotificationBroadcast()
 * for where this is dispatched. `ShouldBroadcastNow` (not `ShouldBroadcast`)
 * since the payload is tiny and this shouldn't wait on a queue worker to
 * feel "real-time".
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public DatabaseNotification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->notification->notifiable_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Spreads the notification's own `data` (event/severity/link/... — see
     * every Notification class's toDatabase()) directly into the payload,
     * so the frontend has everything useNotificationDisplay() needs without
     * a follow-up request.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            ...$this->notification->data,
            'read_at' => $this->notification->read_at,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps Laravel's DatabaseNotification — `data` is whatever the
 * notification's toDatabase() produced (see app/Notifications/Billing),
 * always an `event` key + structured params, never pre-rendered English,
 * so the frontend renders the actual message from its own i18n templates.
 *
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->data, [
            'id' => $this->id,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ]);
    }
}

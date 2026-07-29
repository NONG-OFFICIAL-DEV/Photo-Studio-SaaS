<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EditingTask */
class EditingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'customer' => $this->order->relationLoaded('customer') ? $this->order->customer?->name : null,
            ]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
            ] : null),
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}

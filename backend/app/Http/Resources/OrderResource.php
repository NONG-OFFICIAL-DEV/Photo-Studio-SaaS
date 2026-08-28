<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'booking_id' => $this->booking_id,
            'branch_id' => $this->branch_id,
            'status' => $this->status?->value,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'editing_task' => $this->whenLoaded('editingTask', fn () => $this->editingTask ? new EditingTaskResource($this->editingTask) : null),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

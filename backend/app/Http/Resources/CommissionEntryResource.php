<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CommissionEntry */
class CommissionEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'order_id' => $this->order_id,
            'amount' => (float) $this->amount,
            'earned_date' => $this->earned_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'method' => $this->method?->value,
            'paid_at' => $this->paid_at,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}

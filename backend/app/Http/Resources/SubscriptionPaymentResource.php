<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SubscriptionPayment */
class SubscriptionPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'billing_cycle' => $this->billing_cycle?->value,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'paid_at' => $this->paid_at,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ] : null),
        ];
    }
}

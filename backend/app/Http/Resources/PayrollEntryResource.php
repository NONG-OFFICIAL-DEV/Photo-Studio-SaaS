<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PayrollEntry */
class PayrollEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'period_label' => $this->period_label,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'base_pay' => (float) $this->base_pay,
            'commission_total' => (float) $this->commission_total,
            'deductions' => (float) $this->deductions,
            'net_pay' => (float) $this->net_pay,
            'status' => $this->status?->value,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}

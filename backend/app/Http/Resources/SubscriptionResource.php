<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_cycle' => $this->billing_cycle?->value,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_start' => $this->current_period_start,
            'current_period_ends_at' => $this->current_period_ends_at,
            'cancelled_at' => $this->cancelled_at,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'is_usable' => $this->isUsable(),
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'code' => $this->plan->code,
                'price_monthly' => (float) $this->plan->price_monthly,
                'price_quarterly' => $this->plan->price_quarterly !== null ? (float) $this->plan->price_quarterly : null,
                'price_yearly' => $this->plan->price_yearly !== null ? (float) $this->plan->price_yearly : null,
                'max_users' => $this->plan->max_users,
                'storage_limit_gb' => $this->plan->storage_limit_gb,
                'monthly_order_limit' => $this->plan->monthly_order_limit,
                'has_watermark_gallery' => $this->plan->has_watermark_gallery,
                'has_online_gallery' => $this->plan->has_online_gallery,
                'has_reports' => $this->plan->has_reports,
                'has_api_access' => $this->plan->has_api_access,
            ] : null),
        ];
    }
}

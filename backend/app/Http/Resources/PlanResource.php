<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Plan */
class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'price_monthly' => (float) $this->price_monthly,
            'price_quarterly' => (float) $this->price_quarterly,
            'price_yearly' => (float) $this->price_yearly,
            'max_users' => $this->max_users,
            'max_branches' => $this->max_branches,
            'storage_limit_gb' => $this->storage_limit_gb,
            'monthly_order_limit' => $this->monthly_order_limit,
            'has_watermark_gallery' => $this->has_watermark_gallery,
            'has_online_gallery' => $this->has_online_gallery,
            'has_reports' => $this->has_reports,
            'has_api_access' => $this->has_api_access,
            'has_telegram' => $this->has_telegram,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
        ];
    }
}

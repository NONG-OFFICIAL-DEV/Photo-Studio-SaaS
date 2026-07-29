<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tenant */
class AdminTenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription ? [
                'status' => $this->activeSubscription->status->value,
                'billing_cycle' => $this->activeSubscription->billing_cycle?->value,
                'plan' => $this->activeSubscription->relationLoaded('plan') && $this->activeSubscription->plan ? [
                    'id' => $this->activeSubscription->plan->id,
                    'name' => $this->activeSubscription->plan->name,
                    'code' => $this->activeSubscription->plan->code,
                ] : null,
                'trial_ends_at' => $this->activeSubscription->trial_ends_at,
                'current_period_ends_at' => $this->activeSubscription->current_period_ends_at,
            ] : null),
        ];
    }
}

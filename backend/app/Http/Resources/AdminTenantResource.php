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
            'subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription
                ? new SubscriptionResource($this->activeSubscription)
                : null),
        ];
    }
}

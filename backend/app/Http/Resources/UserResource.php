<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? asset('storage/'.$this->avatar_path) : null,
            'locale' => $this->locale,
            'status' => $this->status,
            'pay_type' => $this->pay_type?->value,
            'base_pay' => $this->base_pay !== null ? (float) $this->base_pay : null,
            'commission_rate' => $this->commission_rate !== null ? (float) $this->commission_rate : null,
            'is_super_admin' => $this->is_super_admin,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->getAllPermissions()->pluck('name')
            ),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'created_at' => $this->created_at,
        ];
    }
}

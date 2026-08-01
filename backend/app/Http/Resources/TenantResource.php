<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tenant */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'qr_payment_url' => $this->qr_payment_path ? asset('storage/'.$this->qr_payment_path) : null,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'is_active' => $this->is_active,
            'settings' => $this->settingsWithDefaults(),
            // Never expose telegram_bot_token/telegram_webhook_secret —
            // these are credentials, not a settings-page scalar.
            'telegram' => [
                'connected' => $this->telegramConnected(),
                'bot_username' => $this->telegram_bot_username,
            ],
            'subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription
                ? new SubscriptionResource($this->activeSubscription)
                : null),
            'created_at' => $this->created_at,
        ];
    }
}

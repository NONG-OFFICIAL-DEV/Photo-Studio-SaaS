<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlatformSetting */
class PlatformSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'khqr_image_url' => $this->khqr_image_path ? asset('storage/'.$this->khqr_image_path) : null,
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'payment_instructions' => $this->payment_instructions,
        ];
    }
}

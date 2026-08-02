<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TelegramMessageLog */
class TelegramMessageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'type' => $this->type,
            'subject_label' => $this->subject_label,
            'format' => $this->format,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'sent_by_name' => $this->sent_by_name,
            'created_at' => $this->created_at,
        ];
    }
}

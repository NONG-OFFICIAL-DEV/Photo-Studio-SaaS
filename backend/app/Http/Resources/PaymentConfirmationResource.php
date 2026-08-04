<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaymentConfirmation */
class PaymentConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant?->name),
            'claimed_amount' => $this->claimed_amount !== null ? (float) $this->claimed_amount : null,
            'note' => $this->note,
            'receipt_url' => $this->receipt_path ? asset('storage/'.$this->receipt_path) : null,
            'status' => $this->status,
            'submitted_by_name' => $this->whenLoaded('submittedByUser', fn () => $this->submittedByUser?->name),
            'reviewed_by_name' => $this->whenLoaded('reviewedByUser', fn () => $this->reviewedByUser?->name),
            'reviewed_at' => $this->reviewed_at,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
        ];
    }
}

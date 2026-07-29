<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'addon_id' => $this->addon_id,
            'name' => $this->name,
            'unit_price' => (float) $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => (float) $this->line_total,
        ];
    }
}

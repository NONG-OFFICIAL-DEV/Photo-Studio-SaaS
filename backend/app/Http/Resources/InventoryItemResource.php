<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryItem */
class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'unit' => $this->unit,
            'category' => $this->category,
            'quantity_on_hand' => (float) $this->quantity_on_hand,
            'reorder_threshold' => $this->reorder_threshold !== null ? (float) $this->reorder_threshold : null,
            'is_low_stock' => $this->is_low_stock,
            'is_active' => $this->is_active,
            'movements' => InventoryMovementResource::collection($this->whenLoaded('movements')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

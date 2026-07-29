<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Package */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value !== null ? (float) $this->discount_value : null,
            'override_price' => $this->override_price !== null ? (float) $this->override_price : null,
            'component_total' => $this->component_total,
            'final_price' => $this->final_price,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'components' => PackageComponentResource::collection($this->whenLoaded('components')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

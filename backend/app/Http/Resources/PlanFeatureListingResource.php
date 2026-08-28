<?php

namespace App\Http\Resources;

use App\Models\PlanFeatureListing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlanFeatureListing */
class PlanFeatureListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => ['en' => $this->label_en, 'km' => $this->label_km],
            'value_type' => $this->value_type->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}

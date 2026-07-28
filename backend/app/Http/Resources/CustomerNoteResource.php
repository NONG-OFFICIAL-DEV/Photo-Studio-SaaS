<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerNote */
class CustomerNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'author' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at,
        ];
    }
}

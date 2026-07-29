<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AttendanceRecord */
class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'date' => $this->date,
            'clock_in_at' => $this->clock_in_at,
            'clock_out_at' => $this->clock_out_at,
            'hours_worked' => $this->hours_worked,
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}

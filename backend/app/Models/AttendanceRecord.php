<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'date', 'clock_in_at', 'clock_out_at', 'status', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
        ];
    }

    protected $attributes = ['status' => 'present'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getHoursWorkedAttribute(): ?float
    {
        if (! $this->clock_in_at || ! $this->clock_out_at) {
            return null;
        }

        return round($this->clock_in_at->diffInMinutes($this->clock_out_at) / 60, 2);
    }
}

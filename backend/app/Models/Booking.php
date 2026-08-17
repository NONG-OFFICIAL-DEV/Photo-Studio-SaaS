<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\LocationType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'assigned_user_id', 'type', 'title', 'notes',
        'location_type', 'location_address', 'starts_at', 'ends_at',
        'status', 'cancelled_reason', 'created_by', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => BookingType::class,
            'location_type' => LocationType::class,
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'location_type' => 'studio',
        'status' => 'pending',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Booking History: every create/update/status-change is logged
     * automatically (attribute diffs only), same pattern as Customer.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'starts_at', 'ends_at', 'assigned_user_id', 'location_type', 'location_address', 'cancelled_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('booking');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

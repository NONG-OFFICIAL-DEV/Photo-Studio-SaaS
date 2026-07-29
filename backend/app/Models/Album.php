<?php

namespace App\Models;

use App\Enums\AlbumStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Album extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'order_id', 'name', 'description',
        'status', 'expected_photo_count', 'delivered_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AlbumStatus::class,
            'expected_photo_count' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'draft'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Album History: status/name changes logged automatically — same
     * pattern as Customer/Booking/Order History.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'expected_photo_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('album');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

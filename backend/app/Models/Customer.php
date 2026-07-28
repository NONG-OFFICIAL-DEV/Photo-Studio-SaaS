<?php

namespace App\Models;

use App\Enums\CustomerGender;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'address', 'birthday', 'gender',
        'avatar_path', 'is_favorite', 'is_blacklisted', 'blacklist_reason', 'created_by',
    ];

    /**
     * Postgres inserts don't return unset default columns to the in-memory
     * model (unlike MySQL's implicit re-select), so without this a fresh
     * Customer::create() would report is_favorite/is_blacklisted as null
     * instead of false until the next reload. These mirror the migration's
     * column defaults.
     */
    protected $attributes = [
        'is_favorite' => false,
        'is_blacklisted' => false,
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'gender' => CustomerGender::class,
            'is_favorite' => 'boolean',
            'is_blacklisted' => 'boolean',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Customer History: every create/update/delete is logged automatically
     * (attribute diffs only) — this is the audit trail behind the
     * "Customer History" feature, no separate history table needed.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone', 'address', 'birthday', 'gender', 'is_favorite', 'is_blacklisted', 'blacklist_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

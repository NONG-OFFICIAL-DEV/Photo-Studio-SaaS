<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryItem extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'sku', 'unit', 'category',
        'quantity_on_hand', 'reorder_threshold', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = ['unit' => 'unit', 'quantity_on_hand' => 0, 'is_active' => true];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('moved_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->reorder_threshold !== null && (float) $this->quantity_on_hand <= (float) $this->reorder_threshold;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'quantity_on_hand', 'reorder_threshold', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('inventory_item');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

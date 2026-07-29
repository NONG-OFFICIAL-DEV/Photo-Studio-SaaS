<?php

namespace App\Models;

use App\Enums\DiscountType;
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

class Package extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'discount_type', 'discount_value',
        'override_price', 'is_active', 'sort_order', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'override_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    public function components(): HasMany
    {
        return $this->hasMany(PackageComponent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Sum of the INCLUDED (non-optional) components' live catalog prices —
     * never cached, so a later Service/Add-on price change is reflected
     * immediately without having to re-save the package.
     */
    public function getComponentTotalAttribute(): float
    {
        $this->loadMissing('components.service', 'components.addon');

        return round(
            $this->components
                ->where('is_optional', false)
                ->sum(fn (PackageComponent $component) => $component->line_total),
            2
        );
    }

    /**
     * override_price wins outright when set; otherwise the component total
     * minus the package's own discount (if any), floored at zero.
     */
    public function getFinalPriceAttribute(): float
    {
        if ($this->override_price !== null) {
            return (float) $this->override_price;
        }

        $total = $this->component_total;

        if (! $this->discount_type || $this->discount_value === null) {
            return $total;
        }

        $discount = $this->discount_type === DiscountType::Percent
            ? $total * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return max(0, round($total - $discount, 2));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'discount_type', 'discount_value', 'override_price', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('package');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

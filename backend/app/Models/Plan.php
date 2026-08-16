<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description',
        'price_monthly', 'price_quarterly', 'price_yearly',
        'max_users', 'max_branches', 'storage_limit_gb', 'monthly_order_limit',
        'has_watermark_gallery', 'has_online_gallery', 'has_reports', 'has_api_access', 'has_telegram',
        'trial_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'has_watermark_gallery' => 'boolean',
            'has_online_gallery' => 'boolean',
            'has_reports' => 'boolean',
            'has_api_access' => 'boolean',
            'has_telegram' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * True if any billing cycle actually charges something. False for the
     * seeded Free Trial plan (price_monthly = 0, no quarterly/yearly price
     * at all) — used to keep it out of the tenant self-service "change
     * plan" picker, since it's a one-time onboarding plan, not an ongoing
     * tier a paying tenant should be able to switch back to.
     */
    public function hasPaidPricing(): bool
    {
        return (float) $this->price_monthly > 0
            || (float) ($this->price_quarterly ?? 0) > 0
            || (float) ($this->price_yearly ?? 0) > 0;
    }
}

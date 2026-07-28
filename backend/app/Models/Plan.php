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
        'max_users', 'storage_limit_gb', 'monthly_order_limit',
        'has_watermark_gallery', 'has_online_gallery', 'has_reports', 'has_api_access',
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
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}

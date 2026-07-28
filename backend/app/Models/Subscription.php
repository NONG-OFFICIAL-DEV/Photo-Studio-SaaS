<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'billing_cycle',
        'trial_ends_at', 'current_period_start', 'current_period_ends_at',
        'cancelled_at', 'amount', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUsable(): bool
    {
        return $this->status->isUsable()
            && ($this->status !== SubscriptionStatus::Trial || $this->trial_ends_at?->isFuture());
    }
}

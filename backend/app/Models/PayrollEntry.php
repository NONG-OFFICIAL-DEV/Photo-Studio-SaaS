<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class PayrollEntry extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'period_label', 'period_start', 'period_end',
        'base_pay', 'commission_total', 'deductions', 'net_pay', 'status', 'paid_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'base_pay' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'status' => PayrollStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'draft', 'base_pay' => 0, 'commission_total' => 0, 'deductions' => 0, 'net_pay' => 0];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'base_pay', 'commission_total', 'deductions', 'net_pay'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('payroll_entry');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
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

class Invoice extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'customer_id', 'order_id', 'invoice_number', 'status',
        'issue_date', 'due_date', 'subtotal', 'discount_amount', 'tax_rate',
        'tax_amount', 'total', 'amount_paid', 'notes', 'voided_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'amount_paid' => 0,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getBalanceDueAttribute(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    /**
     * Invoice History: status/total/payment changes logged automatically —
     * same pattern as Customer/Booking/Order History.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'amount_paid', 'voided_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('invoice');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->tenant_id = $this->tenant_id;
    }
}

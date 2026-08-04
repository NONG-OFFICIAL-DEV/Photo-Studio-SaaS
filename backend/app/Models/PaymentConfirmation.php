<?php

namespace App\Models;

use App\Enums\PaymentConfirmationStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentConfirmation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'claimed_amount', 'note', 'receipt_path',
        'status', 'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note', 'linked_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentConfirmationStatus::class,
            'claimed_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function linkedPayment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'linked_payment_id');
    }
}

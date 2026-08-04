<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\PaymentConfirmationStatus;
use App\Exceptions\ApiException;
use App\Models\PaymentConfirmation;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\PaymentClaimedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * The "I've paid" loop for manual bank transfers against the platform's
 * KHQR/bank details (see PlatformSetting): a tenant submits a claim,
 * every super admin is notified, and an admin either confirms it (which
 * actually renews the subscription via SubscriptionService::renew() —
 * the exact same path as the existing manual Renew button) or rejects it
 * with no side effects.
 */
class PaymentConfirmationService
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    public function submit(Tenant $tenant, User $submittedBy, ?float $amount, ?string $note, ?UploadedFile $receipt): PaymentConfirmation
    {
        $subscription = $tenant->activeSubscription()->firstOrFail();

        $receiptPath = $receipt?->store("tenants/{$tenant->id}/payment-receipts", 'public');

        $claim = PaymentConfirmation::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'claimed_amount' => $amount,
            'note' => $note,
            'receipt_path' => $receiptPath,
            'status' => PaymentConfirmationStatus::Pending,
            'submitted_by' => $submittedBy->id,
        ]);

        // withoutGlobalScopes() is required — this runs inside the
        // submitting tenant's own request context, where TenantScope is
        // active and would otherwise silently filter out every super
        // admin (they all have tenant_id = null). See the identical note
        // on SubscriptionService::superAdmins().
        $superAdmins = User::withoutGlobalScopes()->where('is_super_admin', true)->get();
        if ($superAdmins->isNotEmpty()) {
            Notification::send($superAdmins, new PaymentClaimedNotification($claim));
        }

        return $claim;
    }

    public function pending(): LengthAwarePaginator
    {
        return PaymentConfirmation::query()
            ->withoutGlobalScopes()
            ->where('status', PaymentConfirmationStatus::Pending)
            ->with(['tenant', 'submittedByUser'])
            ->latest('created_at')
            ->paginate(20);
    }

    public function pendingFor(Tenant $tenant): ?PaymentConfirmation
    {
        return PaymentConfirmation::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', PaymentConfirmationStatus::Pending)
            ->latest('created_at')
            ->first();
    }

    public function confirm(PaymentConfirmation $claim, User $admin, ?BillingCycle $cycle): PaymentConfirmation
    {
        $this->assertPending($claim);

        $subscription = $this->subscriptions->renew($claim->subscription, $cycle, $admin);

        $payment = SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->latest('paid_at')
            ->first();

        $claim->update([
            'status' => PaymentConfirmationStatus::Confirmed,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'linked_payment_id' => $payment?->id,
        ]);

        return $claim->fresh();
    }

    public function reject(PaymentConfirmation $claim, User $admin, ?string $note): PaymentConfirmation
    {
        $this->assertPending($claim);

        $claim->update([
            'status' => PaymentConfirmationStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $claim->fresh();
    }

    protected function assertPending(PaymentConfirmation $claim): void
    {
        if ($claim->status !== PaymentConfirmationStatus::Pending) {
            throw new ApiException(422, 'This payment claim has already been reviewed.', 'PAYMENT_CLAIM_ALREADY_REVIEWED');
        }
    }
}

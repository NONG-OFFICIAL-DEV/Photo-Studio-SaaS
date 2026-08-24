<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Exceptions\ApiException;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\SubscriptionExpiredNotification;
use App\Notifications\Billing\SubscriptionExpiringSoonNotification;
use App\Notifications\Billing\SubscriptionReactivatedNotification;
use App\Notifications\Billing\SubscriptionRenewedNotification;
use App\Notifications\Billing\SubscriptionSuspendedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

/**
 * Subscription lifecycle for both the tenant self-service Billing page and
 * the Super Admin's override actions on a tenant's subscription — every
 * method here optionally takes the acting admin ($actor), null meaning
 * "the tenant did this to their own subscription".
 *
 * No real payment gateway is integrated (see the create_subscription_payments
 * migration docblock): renew() simulates a successful payment immediately.
 */
class SubscriptionService
{
    public function usage(Tenant $tenant): array
    {
        return [
            'users_count' => User::query()->where('tenant_id', $tenant->id)->count(),
            'orders_this_month_count' => Order::query()
                ->where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'branches_count' => Branch::query()->where('tenant_id', $tenant->id)->count(),
        ];
    }

    public function paymentHistory(Tenant $tenant): Collection
    {
        return SubscriptionPayment::query()
            ->where('tenant_id', $tenant->id)
            ->with(['plan', 'recordedBy'])
            ->latest('paid_at')
            ->get();
    }

    /**
     * Guards User::create() for a new employee — the plan's max_users is
     * otherwise pure display metadata (see Plan model), so this is the only
     * place it's actually enforced. A null max_users means unlimited.
     */
    public function assertCanAddUser(Tenant $tenant): void
    {
        $maxUsers = $tenant->activeSubscription?->plan?->max_users;

        if ($maxUsers === null) {
            return;
        }

        $currentCount = User::query()->where('tenant_id', $tenant->id)->count();

        if ($currentCount >= $maxUsers) {
            throw new ApiException(422, "Your plan allows up to {$maxUsers} users. Upgrade your plan to add more employees.", 'USER_LIMIT_REACHED', ['maxUsers' => $maxUsers]);
        }
    }

    /**
     * Guards Branch::create() the same way assertCanAddUser() guards
     * User::create() — a null max_branches means unlimited. Counts ALL
     * non-trashed branches regardless of is_active: deactivating a branch
     * does not free a quota slot, only deleting one does (mirrors
     * assertCanAddUser's "deactivated employees still count" behavior).
     */
    public function assertCanAddBranch(Tenant $tenant): void
    {
        $maxBranches = $tenant->activeSubscription?->plan?->max_branches;

        if ($maxBranches === null) {
            return;
        }

        $currentCount = Branch::query()->where('tenant_id', $tenant->id)->count();

        if ($currentCount >= $maxBranches) {
            throw new ApiException(422, "Your plan allows up to {$maxBranches} branches. Upgrade your plan to add more locations.", 'BRANCH_LIMIT_REACHED', ['maxBranches' => $maxBranches]);
        }
    }

    /**
     * Simulates a successful payment for one billing cycle: extends the
     * period (from "now" if already lapsed, otherwise from the current
     * period's end so paying early doesn't lose remaining time), snapshots
     * a SubscriptionPayment row, and reactivates the subscription —
     * including reversing a pending cancellation, since paying again is an
     * unambiguous signal the tenant wants to continue.
     *
     * Deliberately refuses to "renew" a plan with no real price on the
     * requested cycle (the seeded Free Trial plan is exactly this: a $0
     * price_monthly, no quarterly/yearly price at all) — without this
     * check, a tenant could click Renew forever on a free plan, flipping
     * status to Active and never actually converting to a paid one.
     */
    public function renew(Subscription $subscription, ?BillingCycle $cycle, ?User $actor): Subscription
    {
        $plan = $subscription->plan ?? $subscription->plan()->firstOrFail();
        $cycle ??= $subscription->billing_cycle ?? BillingCycle::Monthly;
        $amount = $this->priceForCycle($plan, $cycle);

        if ($amount === null) {
            throw new ApiException(422, "\"{$plan->name}\" isn't available on a {$cycle->value} billing cycle.", 'BILLING_CYCLE_NOT_AVAILABLE', ['plan' => $plan->name, 'cycle' => $cycle->value]);
        }

        if ($amount <= 0) {
            throw new ApiException(422, "\"{$plan->name}\" is a free plan and has nothing to renew — choose a paid plan first.", 'PLAN_HAS_NOTHING_TO_RENEW', ['plan' => $plan->name]);
        }

        $periodStart = $subscription->current_period_ends_at?->isFuture()
            ? $subscription->current_period_ends_at
            : now();
        $periodEnd = $periodStart->copy()->addMonths($cycle->months());

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => $cycle,
            'current_period_start' => $periodStart,
            'current_period_ends_at' => $periodEnd,
            'amount' => $amount,
            'cancelled_at' => null,
            // A new period should get its own fresh expiring-soon warning
            // rather than staying silenced by the previous period's.
            'expiring_soon_notified_at' => null,
        ]);

        SubscriptionPayment::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'amount' => $amount,
            'billing_cycle' => $cycle,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'paid_at' => now(),
            'recorded_by' => $actor?->id,
        ]);

        $this->logAudit($subscription, $actor, "Subscription renewed (\"{$plan->name}\", \${$amount})");

        $subscription = $subscription->fresh('plan');
        $this->notifyOwners($subscription, new SubscriptionRenewedNotification($subscription));

        return $subscription;
    }

    /**
     * Switches which plan the subscription is billed against, and
     * optionally its billing cycle in the same call — a tenant picking a
     * new plan on a cycle their old plan didn't need to support is a single
     * user action, not two. Deliberately no proration — this is simulated
     * billing, not a real payment processor — the new price simply takes
     * effect on the next renew().
     *
     * $cycle null keeps whatever cycle the subscription is already on
     * (unchanged default behavior for callers not exposing a cycle picker,
     * e.g. the admin override). Whichever cycle ends up in effect must
     * actually have a price on the target plan — switching to a plan that
     * doesn't sell a quarterly tier while quarterly is selected should
     * fail loudly (BILLING_CYCLE_NOT_AVAILABLE), not silently null out the
     * amount, mirroring renew()'s equivalent guard.
     *
     * Nobody — tenant self-service or admin override — can switch a
     * subscription onto a plan with no real price on any cycle (the Free
     * Trial plan): it's a one-time onboarding plan assigned once at
     * registration (see AuthService::register()), never a selectable
     * ongoing tier. Switching back to it wouldn't shorten a period already
     * paid for, but it WOULD be a way to dodge every future renewal charge.
     */
    public function changePlan(Subscription $subscription, Plan $plan, ?BillingCycle $cycle, ?User $actor): Subscription
    {
        if (! $plan->is_active) {
            throw new ApiException(422, 'This plan is not available.', 'PLAN_NOT_AVAILABLE');
        }

        if (! $plan->hasPaidPricing()) {
            throw new ApiException(422, "\"{$plan->name}\" is not available to switch to — it's a free onboarding plan, not an ongoing tier.", 'PLAN_NOT_SWITCHABLE', ['plan' => $plan->name]);
        }

        $oldPlanName = $subscription->plan?->name ?? 'previous plan';
        $oldCycle = $subscription->billing_cycle;
        $cycle ??= $oldCycle ?? BillingCycle::Monthly;
        $amount = $this->priceForCycle($plan, $cycle);

        if ($amount === null) {
            throw new ApiException(422, "\"{$plan->name}\" isn't available on a {$cycle->value} billing cycle.", 'BILLING_CYCLE_NOT_AVAILABLE', ['plan' => $plan->name, 'cycle' => $cycle->value]);
        }

        $subscription->update([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'amount' => $amount,
        ]);

        $this->logAudit($subscription, $actor, "Plan changed from \"{$oldPlanName}\" ({$oldCycle?->value}) to \"{$plan->name}\" ({$cycle->value})");

        return $subscription->fresh('plan');
    }

    /**
     * Cancel-at-period-end: access continues (isUsable() is untouched)
     * until the scheduled expireDue() sweep sees the period has actually
     * ended, at which point it transitions to Cancelled (not Expired)
     * because cancelled_at is set.
     */
    public function cancel(Subscription $subscription, ?User $actor): Subscription
    {
        if (! $subscription->status->isUsable()) {
            throw new ApiException(422, 'Only an active or trial subscription can be cancelled.', 'SUBSCRIPTION_NOT_CANCELLABLE');
        }

        if ($subscription->cancelled_at) {
            throw new ApiException(422, 'This subscription is already scheduled for cancellation.', 'SUBSCRIPTION_ALREADY_CANCELLING');
        }

        $subscription->update(['cancelled_at' => now()]);

        $this->logAudit($subscription, $actor, 'Subscription cancellation scheduled for period end');

        return $subscription->fresh('plan');
    }

    public function resume(Subscription $subscription, ?User $actor): Subscription
    {
        if (! $subscription->cancelled_at) {
            throw new ApiException(422, 'This subscription is not scheduled for cancellation.', 'SUBSCRIPTION_NOT_SCHEDULED_FOR_CANCELLATION');
        }

        $endsAt = $subscription->status === SubscriptionStatus::Trial
            ? $subscription->trial_ends_at
            : $subscription->current_period_ends_at;

        if ($endsAt && $endsAt->isPast()) {
            throw new ApiException(422, 'This subscription has already ended and cannot be resumed — renew instead.', 'SUBSCRIPTION_ALREADY_ENDED');
        }

        $subscription->update(['cancelled_at' => null]);

        $this->logAudit($subscription, $actor, 'Subscription cancellation reversed');

        return $subscription->fresh('plan');
    }

    /**
     * Admin-only: blocks access regardless of dates (e.g. a payment
     * dispute) without touching the tenant's own is_active flag, which is
     * a separate, more severe admin action (see AdminTenantService).
     */
    public function suspend(Subscription $subscription, User $actor): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Suspended]);

        $this->logAudit($subscription, $actor, 'Subscription suspended');

        $subscription = $subscription->fresh('plan');
        $this->notifyOwners($subscription, new SubscriptionSuspendedNotification($subscription, $actor));

        return $subscription;
    }

    /**
     * Reverses suspend() by recomputing the correct status from the dates
     * alone, so un-suspending never grants more access than the tenant's
     * actual trial/paid period justifies.
     */
    public function reactivate(Subscription $subscription, User $actor): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Suspended) {
            throw new ApiException(422, 'Only a suspended subscription can be reactivated this way.', 'SUBSCRIPTION_NOT_SUSPENDED');
        }

        $status = $this->statusFromDates($subscription);

        $subscription->update(['status' => $status]);

        $this->logAudit($subscription, $actor, "Subscription reactivated as \"{$status->label()}\"");

        $subscription = $subscription->fresh('plan');
        $this->notifyOwners($subscription, new SubscriptionReactivatedNotification($subscription));

        return $subscription;
    }

    /**
     * The scheduled sweep (see app/Console/Commands and routes/console.php)
     * — tenant-agnostic by design, same as InvoiceService::markOverdue().
     */
    public function expireDue(): int
    {
        $due = Subscription::query()
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('status', SubscriptionStatus::Trial->value)->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now()))
                    ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::Active->value)->whereNotNull('current_period_ends_at')->where('current_period_ends_at', '<', now()));
            })
            ->get();

        foreach ($due as $subscription) {
            $becomesExpired = ! $subscription->cancelled_at;

            $subscription->update([
                'status' => $becomesExpired ? SubscriptionStatus::Expired : SubscriptionStatus::Cancelled,
            ]);

            // A tenant who scheduled their own cancellation already knows
            // it's ending — only an unplanned lapse into Expired is a
            // surprise worth notifying about.
            if ($becomesExpired) {
                $this->notifyOwners($subscription->fresh('plan'), new SubscriptionExpiredNotification($subscription), includeSuperAdmins: true);
            }
        }

        return $due->count();
    }

    /**
     * The `subscriptions:notify-expiring` sweep: warns a tenant's Owner (and
     * super admins) once per period, $days before their Trial/Active
     * subscription lapses — expireDue() only reacts after the fact, this is
     * the proactive counterpart. expiring_soon_notified_at guards against
     * re-notifying every day between the threshold and the actual expiry;
     * renew() resets it so a later period gets its own warning.
     */
    public function expiringSoon(int $days = 3): int
    {
        $threshold = now()->addDays($days);

        $subscriptions = Subscription::query()
            ->whereNull('expiring_soon_notified_at')
            ->where(function ($query) use ($threshold) {
                $query->where(fn ($q) => $q->where('status', SubscriptionStatus::Trial->value)->whereNotNull('trial_ends_at')->whereBetween('trial_ends_at', [now(), $threshold]))
                    ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::Active->value)->whereNotNull('current_period_ends_at')->whereBetween('current_period_ends_at', [now(), $threshold]));
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $endsAt = $subscription->status === SubscriptionStatus::Trial
                ? $subscription->trial_ends_at
                : $subscription->current_period_ends_at;

            $daysLeft = max(0, now()->diffInDays($endsAt, false));

            $this->notifyOwners($subscription, new SubscriptionExpiringSoonNotification($subscription, (int) $daysLeft), includeSuperAdmins: true);

            $subscription->update(['expiring_soon_notified_at' => now()]);
        }

        return $subscriptions->count();
    }

    protected function statusFromDates(Subscription $subscription): SubscriptionStatus
    {
        if ($subscription->trial_ends_at?->isFuture()) {
            return SubscriptionStatus::Trial;
        }

        if ($subscription->current_period_ends_at?->isFuture()) {
            return SubscriptionStatus::Active;
        }

        return $subscription->cancelled_at ? SubscriptionStatus::Cancelled : SubscriptionStatus::Expired;
    }

    public function priceForCycle(Plan $plan, BillingCycle $cycle): ?float
    {
        $price = match ($cycle) {
            BillingCycle::Monthly => $plan->price_monthly,
            BillingCycle::Quarterly => $plan->price_quarterly,
            BillingCycle::Yearly => $plan->price_yearly,
        };

        return $price !== null ? (float) $price : null;
    }

    protected function logAudit(Subscription $subscription, ?User $actor, string $description): void
    {
        activity('audit')
            ->performedOn($subscription)
            ->causedBy($actor)
            ->tap(fn ($a) => $a->tenant_id = $subscription->tenant_id)
            ->log($description);
    }

    /**
     * Notifies the tenant's Owner(s), and optionally every super admin, of a
     * billing event. Explicitly sets the permissions team ID rather than
     * trusting the caller's request context — this is called from both a
     * tenant-scoped request (BillingController, team ID already correct)
     * and an admin-scoped one (AdminTenantController, no team ID set at
     * all), so it can't rely on either.
     */
    protected function notifyOwners(Subscription $subscription, \Illuminate\Notifications\Notification $notification, bool $includeSuperAdmins = false): void
    {
        $recipients = $this->ownersOf($subscription->tenant_id);

        if ($includeSuperAdmins) {
            $recipients = $recipients->merge($this->superAdmins());
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    protected function ownersOf(string $tenantId): Collection
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($query) => $query->where('name', TenantRole::Owner->value))
            ->get();
    }

    /**
     * withoutGlobalScopes() is required here, not defensive decoration —
     * this can run inside a tenant-scoped HTTP request (e.g. a tenant's own
     * self-service renew()), where TenantScope is active and would
     * otherwise silently filter every super admin out (they all have
     * tenant_id = null, so a scope constraining to "the current tenant"
     * matches none of them).
     */
    protected function superAdmins(): Collection
    {
        return User::withoutGlobalScopes()->where('is_super_admin', true)->get();
    }
}

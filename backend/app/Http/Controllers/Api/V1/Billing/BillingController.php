<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ChangePlanRequest;
use App\Http\Requests\Billing\RenewSubscriptionRequest;
use App\Http\Requests\Billing\SubmitPaymentClaimRequest;
use App\Http\Resources\PaymentConfirmationResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\PlatformSettingResource;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\PaymentConfirmationService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant self-service billing. Deliberately mounted OUTSIDE the
 * `subscription.active` middleware (see routes/api/v1.php) — a tenant
 * whose subscription has already lapsed must still be able to reach this
 * to renew, or they'd be locked out with no way to fix it themselves.
 */
class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SubscriptionService $subscriptions,
        protected PaymentConfirmationService $paymentConfirmations,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->with('plan')->firstOrFail();
        $pendingClaim = $this->paymentConfirmations->pendingFor($tenant);

        return $this->success([
            'subscription' => new SubscriptionResource($subscription),
            'usage' => $this->subscriptions->usage($tenant),
            'payment_info' => new PlatformSettingResource(PlatformSetting::current()),
            'pending_payment_claim' => $pendingClaim ? new PaymentConfirmationResource($pendingClaim) : null,
        ]);
    }

    public function submitPaymentClaim(SubmitPaymentClaimRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($this->paymentConfirmations->pendingFor($tenant)) {
            return $this->error('You already have a payment claim under review.', 422, [], 'PAYMENT_CLAIM_ALREADY_PENDING');
        }

        $claim = $this->paymentConfirmations->submit(
            $tenant,
            $request->user(),
            $request->validated('claimed_amount'),
            $request->validated('note'),
            $request->file('receipt'),
        );

        return $this->created(new PaymentConfirmationResource($claim), 'Payment claim submitted. An admin will review it shortly.');
    }

    public function plans(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        // Excludes plans with no real price on any cycle (the Free Trial
        // plan) — it's a one-time onboarding plan, not something a tenant
        // should be able to self-service "switch back to" (see
        // SubscriptionService::changePlan(), which enforces this too).
        $plans = Plan::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('price_monthly', '>', 0)
                    ->orWhere('price_quarterly', '>', 0)
                    ->orWhere('price_yearly', '>', 0);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(PlanResource::collection($plans));
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        return $this->success(SubscriptionPaymentResource::collection($this->subscriptions->paymentHistory($request->user()->tenant)));
    }

    public function renew(RenewSubscriptionRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->firstOrFail();
        $cycle = $request->validated('billing_cycle') ? BillingCycle::from($request->validated('billing_cycle')) : null;

        $subscription = $this->subscriptions->renew($subscription, $cycle, null);

        return $this->success(new SubscriptionResource($subscription), 'Subscription renewed successfully.');
    }

    public function changePlan(ChangePlanRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->firstOrFail();
        $plan = Plan::findOrFail($request->validated('plan_id'));
        $cycle = $request->validated('billing_cycle') ? BillingCycle::from($request->validated('billing_cycle')) : null;

        $subscription = $this->subscriptions->changePlan($subscription, $plan, $cycle, null);

        return $this->success(new SubscriptionResource($subscription), 'Plan changed successfully.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->firstOrFail();
        $subscription = $this->subscriptions->cancel($subscription, null);

        return $this->success(new SubscriptionResource($subscription), 'Your subscription will end at the close of the current period.');
    }

    public function resume(Request $request): JsonResponse
    {
        $this->authorizeBilling($request);

        $tenant = $request->user()->tenant;
        $subscription = $tenant->activeSubscription()->firstOrFail();
        $subscription = $this->subscriptions->resume($subscription, null);

        return $this->success(new SubscriptionResource($subscription), 'Cancellation reversed — your subscription will continue.');
    }

    protected function authorizeBilling(Request $request): void
    {
        abort_unless($request->user()->can('tenant.billing.manage'), 403);
    }
}

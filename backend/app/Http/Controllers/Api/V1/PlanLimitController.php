<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately separate from Billing: BillingController is gated behind
 * `tenant.billing.manage` (Owner-only by default), but any tenant user
 * should be able to see "you're at your plan's limit" nudges on pages like
 * Employees/Orders regardless of their role — so this exposes only the
 * limit numbers, no pricing/payment data, with no extra permission check.
 */
class PlanLimitController extends Controller
{
    use ApiResponse;

    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $plan = $tenant->activeSubscription?->plan;
        $usage = $this->subscriptions->usage($tenant);

        return $this->success([
            'max_users' => $plan?->max_users,
            'users_count' => $usage['users_count'],
            'monthly_order_limit' => $plan?->monthly_order_limit,
            'orders_this_month_count' => $usage['orders_this_month_count'],
        ]);
    }
}

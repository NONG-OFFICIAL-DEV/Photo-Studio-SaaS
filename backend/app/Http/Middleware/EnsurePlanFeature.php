<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route group behind one of the Plan model's boolean feature flags
 * (has_reports, has_api_access, ...). Separate from subscription.active
 * (which gates on payment status) and permission checks (which gate on
 * role) — this gates on plan TIER, independent of both.
 */
class EnsurePlanFeature
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return $next($request);
        }

        $plan = $user->tenant?->activeSubscription?->plan;

        if (! $plan || ! $plan->{$feature}) {
            return $this->error("This feature isn't included in your current plan. Upgrade to unlock it.", 403);
        }

        return $next($request);
    }
}

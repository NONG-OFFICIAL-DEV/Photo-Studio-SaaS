<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates tenant-scoped routes behind an in-effect subscription.
 * Runs after IdentifyTenant. Super admins bypass this check entirely.
 *
 * Checked against real dates (below) rather than only the stored `status`
 * column, since the `subscriptions:expire` scheduled command (runs daily,
 * see routes/console.php) that flips trial/active -> expired may not have
 * run yet at the exact expiry instant. Suspended/Cancelled/Expired are
 * still checked directly here too, for whenever the column IS already
 * accurate (e.g. right after that command runs, or an admin's explicit
 * suspend/cancel action).
 */
class EnsureSubscriptionActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_super_admin) {
            return $next($request);
        }

        $subscription = $user->tenant?->activeSubscription;

        if (! $subscription) {
            return $this->error('No subscription found for this studio. Please contact support.', 402);
        }

        if (in_array($subscription->status, [SubscriptionStatus::Suspended, SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
            return $this->error(
                "Your subscription is {$subscription->status->value}. Please renew to continue.",
                402,
                ['subscription_status' => $subscription->status->value]
            );
        }

        if ($subscription->status === SubscriptionStatus::Trial && $subscription->trial_ends_at?->isPast()) {
            return $this->error('Your free trial has ended. Please choose a plan to continue.', 402, [
                'subscription_status' => SubscriptionStatus::Expired->value,
            ]);
        }

        if ($subscription->status === SubscriptionStatus::Active && $subscription->current_period_ends_at?->isPast()) {
            return $this->error('Your subscription has expired. Please renew to continue.', 402, [
                'subscription_status' => SubscriptionStatus::Expired->value,
            ]);
        }

        return $next($request);
    }
}

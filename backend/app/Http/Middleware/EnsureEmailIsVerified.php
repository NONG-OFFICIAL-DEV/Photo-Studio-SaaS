<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates specific SENSITIVE actions (inviting/editing team members, tenant
 * settings changes) behind a verified email address — deliberately applied
 * per-route, not to the whole tenant-scoped group, so an unverified user
 * can still use the rest of the app normally while nudged to verify before
 * doing anything account-access-related. Super admins bypass this check
 * entirely (same convention as EnsureSubscriptionActive/IdentifyTenant).
 *
 * Deliberately NOT applied to billing routes — this codebase already
 * treats "can the tenant always reach billing to fix their own account" as
 * non-negotiable (see the billing route group's own docblock), and the
 * same reasoning holds here: an unverified tenant must still be able to
 * pay you, that's not the kind of "sensitive" this guards against.
 *
 * Deliberately does NOT use Laravel's built-in `verified` middleware —
 * that one's default JSON response doesn't match this app's `success`/
 * `code` envelope (App\Traits\ApiResponse), which every other gate in this
 * app (tenant, subscription, plan-feature) already returns.
 */
class EnsureEmailIsVerified
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_super_admin || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $this->error('Please verify your email address to continue.', 403, [], 'EMAIL_NOT_VERIFIED');
    }
}

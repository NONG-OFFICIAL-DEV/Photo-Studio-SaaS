<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the platform admin panel (/api/v1/admin/*). Deliberately runs
 * standalone behind only `auth:api` — NOT the `tenant`/`subscription.active`
 * group — since super admins have no tenant_id and operate across every
 * tenant (see IdentifyTenant's own super-admin exemption).
 */
class EnsureSuperAdmin
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            return $this->error('This action is restricted to platform administrators.', 403, [], 'SUPER_ADMIN_ONLY');
        }

        return $next($request);
    }
}

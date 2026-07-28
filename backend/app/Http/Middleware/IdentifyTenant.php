<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves "which tenant is this request operating as" from the
 * authenticated JWT user and binds it to the request lifecycle:
 *
 *  - TenantContext, consumed by the TenantScope global scope so every
 *    Eloquent query on a tenant-owned model is auto-filtered.
 *  - Spatie's PermissionRegistrar team id, so role/permission checks
 *    (hasRole, can, ...) are scoped to the same tenant.
 *
 * Must run AFTER the `auth:api` middleware so Auth::user() is populated.
 * Super admin users (is_super_admin = true, tenant_id = null) are exempt —
 * they operate across all tenants from the platform panel.
 */
class IdentifyTenant
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->tenant_id || ! $user->tenant) {
            return $this->error('No tenant associated with this account.', 403);
        }

        if (! $user->tenant->is_active) {
            return $this->error('This studio account has been suspended by the platform.', 403);
        }

        app(TenantContext::class)->set($user->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}

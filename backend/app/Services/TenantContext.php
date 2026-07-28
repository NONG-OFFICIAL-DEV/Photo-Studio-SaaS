<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Request-scoped holder for "which tenant is this request operating as".
 * Populated by App\Http\Middleware\IdentifyTenant after the JWT is resolved.
 * Bound as a singleton — one instance per request lifecycle.
 */
class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}

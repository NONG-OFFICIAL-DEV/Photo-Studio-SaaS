<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model. Registers the global TenantScope
 * (query-time isolation) and auto-fills tenant_id on create (write-time
 * isolation) from the resolved TenantContext, so callers never have to
 * remember to pass it explicitly.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $context = app(TenantContext::class);

                if ($context->check()) {
                    $model->tenant_id = $context->id();
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

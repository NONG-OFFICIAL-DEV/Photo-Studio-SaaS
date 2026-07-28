<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically constrains every query on a tenant-scoped model to the
 * tenant resolved for the current request. This is the single mechanism
 * that guarantees tenant data isolation at the query layer — no controller
 * or service is ever trusted to remember a manual ->where('tenant_id', ...).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->check()) {
            $builder->where($model->getTable().'.tenant_id', $context->id());
        }
    }
}

<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Branch;
use App\Models\Tenant;

/**
 * Auto-resolves branch_id on create so a tenant never has to think about
 * branches until they actually have more than one: 0 branches -> stays
 * null, exactly 1 -> silently assigned, 2+ -> the caller must supply one.
 *
 * Only called on CREATE. A client-supplied $branchId is trusted as already
 * belonging to the tenant — the calling FormRequest validates that via
 * Rule::exists('branches', 'id')->where('tenant_id', ...), the same
 * pattern already used for customer_id/assigned_user_id, so it isn't
 * re-checked here.
 */
class BranchResolutionService
{
    public function resolveForCreate(Tenant $tenant, ?string $branchId): ?string
    {
        if ($branchId) {
            return $branchId;
        }

        $branchIds = Branch::where('tenant_id', $tenant->id)->pluck('id');

        if ($branchIds->count() === 1) {
            return $branchIds->first();
        }

        if ($branchIds->count() > 1) {
            throw new ApiException(422, 'Please select a branch — this studio has more than one location.', 'BRANCH_REQUIRED');
        }

        return null;
    }
}

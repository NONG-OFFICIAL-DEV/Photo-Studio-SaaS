<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Branch;
use App\Models\Tenant;

/**
 * Auto-resolves branch_id on create so a tenant never has to think about
 * branches until they actually have more than one ACTIVE branch: 0 active
 * branches -> stays null, exactly 1 -> silently assigned, 2+ -> the caller
 * must supply one. Counts active branches only — this must match the
 * frontend's branch picker (see BranchController::index()'s active-only
 * default and useBranchStore), which also only ever shows/counts active
 * branches. Counting inactive branches here would let this "requires a
 * branch" case fire even when the picker has nothing to offer (e.g. 1
 * active + 1 inactive branch shows no picker at all, but a naive count of
 * 2 would still demand a choice with no way to make one).
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

        $branchIds = Branch::where('tenant_id', $tenant->id)->where('is_active', true)->pluck('id');

        if ($branchIds->count() === 1) {
            return $branchIds->first();
        }

        if ($branchIds->count() > 1) {
            throw new ApiException(422, 'Please select a branch — this studio has more than one location.', 'BRANCH_REQUIRED');
        }

        return null;
    }
}

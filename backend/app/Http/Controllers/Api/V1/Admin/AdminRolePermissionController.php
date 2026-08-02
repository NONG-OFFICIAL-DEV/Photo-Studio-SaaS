<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\SyncRoleDefaultPermissionsAction;
use App\Enums\TenantRole;
use App\Http\Controllers\Controller;
use App\Services\RolePermissionDefaultsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Lets a platform super admin edit which permissions each baseline role
 * (Manager..Viewer) gets by default, applied live across every existing
 * tenant — no code deploy needed. Owner is deliberately excluded: it
 * always has every permission, non-editable, so there's always at least
 * one fully-privileged account per tenant.
 */
class AdminRolePermissionController extends Controller
{
    use ApiResponse;

    public function __construct(protected RolePermissionDefaultsService $defaults)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success([
            'catalog' => config('permissions.catalog'),
            'roles' => collect(TenantRole::cases())->map(fn (TenantRole $role) => [
                'role' => $role->value,
                'label' => $role->label(),
                'locked' => $role === TenantRole::Owner,
                'permissions' => $this->defaults->get($role),
            ])->values(),
        ]);
    }

    public function update(Request $request, string $role, SyncRoleDefaultPermissionsAction $action): JsonResponse
    {
        $tenantRole = TenantRole::tryFrom($role);

        abort_if(! $tenantRole, 404, 'Unknown role.');
        abort_if($tenantRole === TenantRole::Owner, 422, 'The Owner role always has full access and cannot be edited.');

        $validCatalog = Arr::flatten(config('permissions.catalog'));

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in($validCatalog)],
        ]);

        $action->execute($tenantRole, $validated['permissions']);

        return $this->success([
            'role' => $tenantRole->value,
            'permissions' => $validated['permissions'],
        ], 'Role permissions updated.');
    }
}

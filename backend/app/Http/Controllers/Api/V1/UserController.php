<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayType;
use App\Enums\TenantRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserEmploymentRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BranchResolutionService;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(protected SubscriptionService $subscriptions, protected BranchResolutionService $branches)
    {
    }

    /**
     * Tenant staff list — backs both the "assign photographer" pickers
     * scattered across Bookings/Orders/Attendance/Commission/Payroll AND
     * the Employees management tab. Defaults to active-only so a
     * deactivated employee silently stops being assignable everywhere
     * without every picker needing its own filter; the management tab
     * passes include_inactive=1 to see (and reactivate) everyone.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.view'), 403);

        $query = User::query()->orderBy('name')->with('roles');

        if (! $request->boolean('include_inactive')) {
            $query->where('status', UserStatus::Active);
        }

        return $this->success(UserResource::collection($query->get()));
    }

    /**
     * Creates a new employee for the current tenant, setting their password
     * directly (no invite-email flow — the app has no working mailer wired
     * up yet). Blocked once the plan's max_users limit is reached.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $this->subscriptions->assertCanAddUser($tenant);

        $branchId = $this->branches->resolveForCreate($tenant, $request->validated('branch_id'));

        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make($request->validated('password')),
            'pay_type' => $request->validated('pay_type') ?? PayType::Salary->value,
            'base_pay' => $request->validated('base_pay'),
            'commission_rate' => $request->validated('commission_rate'),
        ]);

        $user->assignRole($request->validated('role'));

        return $this->success(new UserResource($user->fresh('roles')), 'Employee created successfully.', 201);
    }

    /**
     * Scoped to employment fields only (pay_type/base_pay/commission_rate)
     * — Phase 10's Employee Management. Reuses the existing `users.update`
     * permission; full profile/role editing remains a later phase.
     */
    public function update(UpdateUserEmploymentRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->success(new UserResource($user->fresh()), 'Employee profile updated successfully.');
    }

    /**
     * Soft, reversible alternative to actually deleting an employee — a
     * true delete would either cascade-destroy every booking/commission/
     * payroll/attendance record that references them, or fail outright on
     * the foreign keys, neither of which a tenant wants for someone who
     * just left. Deactivating blocks login (AuthService::login() already
     * checks isActive()) and drops them from active-employee pickers
     * (UserController::index()) while keeping all their history intact —
     * reactivate() reverses it. Reuses the existing `users.delete`
     * permission (Owner has it by default via the wildcard; Manager
     * deliberately doesn't — same boundary as today).
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.delete'), 403);

        if ($user->id === $request->user()->id) {
            return $this->error('You cannot deactivate your own account.', 422, [], 'CANNOT_DEACTIVATE_SELF');
        }

        if ($user->hasRole(TenantRole::Owner->value) && $this->activeOwnerCount($user->tenant_id) <= 1) {
            return $this->error('A studio must have at least one active owner.', 422, [], 'CANNOT_DEACTIVATE_LAST_OWNER');
        }

        $user->update(['status' => UserStatus::Inactive]);

        return $this->success(new UserResource($user->fresh('roles')), 'Employee deactivated successfully.');
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.delete'), 403);

        $user->update(['status' => UserStatus::Active]);

        return $this->success(new UserResource($user->fresh('roles')), 'Employee reactivated successfully.');
    }

    protected function activeOwnerCount(string $tenantId): int
    {
        return User::where('tenant_id', $tenantId)
            ->where('status', UserStatus::Active)
            ->whereHas('roles', fn ($query) => $query->where('name', TenantRole::Owner->value))
            ->count();
    }
}

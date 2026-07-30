<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserEmploymentRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    /**
     * Lightweight tenant staff list — currently just backs the
     * "assign photographer" picker on Bookings. Full user management
     * (invite/deactivate/roles UI) is a later phase.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('users.view'), 403);

        $users = User::query()->orderBy('name')->with('roles')->get();

        return $this->success(UserResource::collection($users));
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

        $user = User::create([
            'tenant_id' => $tenant->id,
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
}

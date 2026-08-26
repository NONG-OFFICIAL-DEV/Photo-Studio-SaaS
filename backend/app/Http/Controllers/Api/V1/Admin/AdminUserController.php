<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminUserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminUserService $users) {}

    public function index(Tenant $tenant): JsonResponse
    {
        return $this->success(UserResource::collection($this->users->usersFor($tenant)));
    }

    public function deactivate(Tenant $tenant, User $user): JsonResponse
    {
        return $this->success(new UserResource($this->users->deactivate($tenant, $user)), 'Employee deactivated successfully.');
    }

    public function reactivate(Tenant $tenant, User $user): JsonResponse
    {
        return $this->success(new UserResource($this->users->reactivate($tenant, $user)), 'Employee reactivated successfully.');
    }

    public function sendPasswordReset(Tenant $tenant, User $user): JsonResponse
    {
        $this->users->sendPasswordReset($tenant, $user);

        return $this->success(null, 'Password reset link sent.');
    }
}

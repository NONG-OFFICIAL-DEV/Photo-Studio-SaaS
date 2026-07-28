<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTO\RegisterTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService)
    {
    }

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        $payload = $this->authService->register(RegisterTenantData::fromRequest($request));

        return $this->created($this->withAuthPayload($payload), 'Studio registered successfully. Please verify your email.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        return $this->success($this->withAuthPayload($payload), 'Logged in successfully.');
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->success(null, 'Logged out successfully.');
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->authService->refresh();

        return $this->success($this->withAuthPayload($payload), 'Token refreshed successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->me();
        $this->loadUserRoleContext($user);

        return $this->success(new UserResource($user), 'Current user fetched.');
    }

    protected function withAuthPayload(array $payload): array
    {
        /** @var User $user */
        $user = $payload['user'];
        $this->loadUserRoleContext($user);

        return [
            'user' => new UserResource($user),
            'access_token' => $payload['access_token'],
            'token_type' => $payload['token_type'],
            'expires_in' => $payload['expires_in'],
        ];
    }

    /**
     * Ensures roles/permissions in the response reflect the user's own
     * tenant team scope, then eager loads them for the resource.
     */
    protected function loadUserRoleContext(User $user): void
    {
        if ($user->tenant_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }

        $user->load(['roles', 'tenant']);
    }
}

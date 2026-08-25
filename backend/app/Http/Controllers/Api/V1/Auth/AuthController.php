<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTO\GoogleRegisterData;
use App\DTO\RegisterTenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\GoogleRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Google\GoogleIdTokenVerifierInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService,
        protected GoogleIdTokenVerifierInterface $googleVerifier,
    ) {}

    public function register(RegisterTenantRequest $request): JsonResponse
    {
        $payload = $this->authService->register(RegisterTenantData::fromRequest($request));

        return $this->created($this->withAuthPayload($payload), 'Studio registered successfully. Please verify your email.');
    }

    /**
     * Login/link only — if this Google account has no matching user yet,
     * responds with `requires_registration: true` (not an error) so the
     * frontend can prompt for studio details and call googleRegister().
     */
    public function googleAuth(GoogleAuthRequest $request): JsonResponse
    {
        $google = $this->googleVerifier->verify($request->string('id_token')->toString());
        $payload = $this->authService->registerOrLoginWithGoogle($google);

        if (isset($payload['requires_registration'])) {
            return $this->success($payload, 'This Google account is not registered yet.');
        }

        return $this->success($this->withAuthPayload($payload), 'Logged in successfully.');
    }

    public function googleRegister(GoogleRegisterRequest $request): JsonResponse
    {
        $google = $this->googleVerifier->verify($request->string('id_token')->toString());
        $payload = $this->authService->registerOrLoginWithGoogle($google, GoogleRegisterData::fromRequest($request));

        return $this->created($this->withAuthPayload($payload), 'Studio registered successfully.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        if (isset($payload['requires_two_factor'])) {
            return $this->success($payload, 'Two-factor authentication code required.');
        }

        return $this->success($this->withAuthPayload($payload), 'Logged in successfully.');
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $payload = $this->authService->verifyTwoFactor(
            $request->string('two_factor_token')->toString(),
            $request->string('code')->toString(),
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

    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $user = $this->authService->updateEmail($request->user(), $request->string('email')->toString());
        $this->loadUserRoleContext($user);

        return $this->success(new UserResource($user), 'Email updated. Please verify your new email address.');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->authService->updatePassword($request->user(), $request->string('password')->toString());

        return $this->success(null, 'Password updated successfully.');
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
     * tenant team scope, then eager loads them for the resource. Also
     * loads the tenant's active subscription/plan so the frontend can read
     * plan feature flags (has_reports, ...) straight off the auth session
     * without a separate request.
     */
    protected function loadUserRoleContext(User $user): void
    {
        if ($user->tenant_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }

        $user->load(['roles', 'tenant.activeSubscription.plan']);
    }
}

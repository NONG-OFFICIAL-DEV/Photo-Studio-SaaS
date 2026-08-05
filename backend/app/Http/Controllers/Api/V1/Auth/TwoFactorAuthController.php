<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Self-service enrollment — scoped to is_super_admin accounts only (see
 * AuthService::login()'s hasTwoFactorEnabled() check for where that's
 * actually enforced at login time; the 403s here just stop a regular
 * tenant user from enabling something that would never be asked for).
 */
class TwoFactorAuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected TwoFactorAuthService $twoFactor)
    {
    }

    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->is_super_admin) {
            throw new AccessDeniedHttpException('Two-factor authentication is only available for super admin accounts.');
        }

        $setup = $this->twoFactor->generateSecret($user);

        return $this->success($setup, 'Scan the QR code with your authenticator app, then confirm with a code.');
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $recoveryCodes = $this->twoFactor->confirm($request->user(), $request->string('code')->toString());

        return $this->success(['recovery_codes' => $recoveryCodes], 'Two-factor authentication enabled. Save these recovery codes somewhere safe.');
    }

    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactor->disable($request->user());

        return $this->success(null, 'Two-factor authentication disabled.');
    }
}

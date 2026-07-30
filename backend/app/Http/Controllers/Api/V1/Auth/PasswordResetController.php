<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService)
    {
    }

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->sendPasswordResetLink($request->string('email')->toString());
        $code = $this->codeForBrokerStatus($status);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error(__($status), 422, [], $code);
        }

        return $this->success(null, __($status), 200, [], $code);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );
        $code = $this->codeForBrokerStatus($status);

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422, [], $code);
        }

        return $this->success(null, __($status), 200, [], $code);
    }

    /**
     * Laravel's password broker returns one of a fixed set of translation
     * keys (Password::RESET_LINK_SENT et al) rather than a literal message —
     * map each to our own stable code so the frontend can translate it too.
     */
    protected function codeForBrokerStatus(string $status): string
    {
        return match ($status) {
            Password::RESET_LINK_SENT => 'PASSWORD_LINK_SENT',
            Password::INVALID_USER => 'PASSWORD_INVALID_USER',
            Password::RESET_THROTTLED => 'PASSWORD_RESET_THROTTLED',
            Password::PASSWORD_RESET => 'PASSWORD_RESET_SUCCESS',
            Password::INVALID_TOKEN => 'PASSWORD_INVALID_TOKEN',
            default => 'PASSWORD_RESET_UNKNOWN',
        };
    }
}

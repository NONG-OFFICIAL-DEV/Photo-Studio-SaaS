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

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error(__($status), 422);
        }

        return $this->success(null, __($status));
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422);
        }

        return $this->success(null, __($status));
    }
}

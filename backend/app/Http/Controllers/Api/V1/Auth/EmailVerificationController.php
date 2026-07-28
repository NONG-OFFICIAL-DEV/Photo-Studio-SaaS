<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    /**
     * Signed link from the verification email. Marks the user verified and
     * redirects the browser into the SPA — no token/session needed here,
     * this is a one-click confirmation from the user's inbox.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);
        $frontend = rtrim(config('app.frontend_url'), '/');

        if (! hash_equals(sha1($user->email), $hash)) {
            return redirect("{$frontend}/email-verified?status=invalid");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect("{$frontend}/email-verified?status=success");
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->success(null, 'Verification link sent.');
    }
}

<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configurePasswordResetUrl();
        $this->configureEmailVerificationUrl();
        $this->configureRateLimiters();

        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }

    /**
     * Password reset emails link into the Vue SPA, which collects the new
     * password and POSTs it to /api/v1/auth/password/reset.
     */
    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $frontend = rtrim(config('app.frontend_url'), '/');

            return "{$frontend}/reset-password?token={$token}&email=".urlencode($user->email);
        });
    }

    /**
     * Verification emails link straight to a signed backend endpoint
     * (one-click confirmation, no SPA round trip needed), which then
     * redirects the browser back into the SPA.
     */
    protected function configureEmailVerificationUrl(): void
    {
        VerifyEmail::createUrlUsing(function (User $user) {
            return URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                now()->addMinutes(60),
                ['id' => $user->getKey(), 'hash' => sha1($user->email)]
            );
        });
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}

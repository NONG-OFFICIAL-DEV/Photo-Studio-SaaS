<?php

namespace App\Providers;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Services\Google\GoogleAuthorizationCodeExchanger;
use App\Services\Google\GoogleAuthorizationCodeExchangerInterface;
use App\Services\Google\GoogleIdTokenVerifier;
use App\Services\Google\GoogleIdTokenVerifierInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleIdTokenVerifierInterface::class, GoogleIdTokenVerifier::class);
        $this->app->bind(GoogleAuthorizationCodeExchangerInterface::class, GoogleAuthorizationCodeExchanger::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configurePasswordResetUrl();
        $this->configureEmailVerificationUrl();
        $this->configureRateLimiters();
        $this->configureBroadcasting();
        $this->configureNotificationBroadcast();

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

    /**
     * Not registered via `channels:` in bootstrap/app.php — that puts
     * /broadcasting/auth behind the default `web` (session/CSRF) middleware,
     * which this JWT-only API has no guard for. `auth:api` matches every
     * other authenticated route instead. Prefixed under `api` (not `api/v1`
     * — versioning a socket-auth-only endpoint isn't meaningful) so the
     * frontend's existing `/api`-based axios client reaches it with no
     * special-cased base URL of its own.
     */
    protected function configureBroadcasting(): void
    {
        Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:api']]);

        require base_path('routes/channels.php');
    }

    /**
     * Every existing Notification class already persists to the `database`
     * channel (see App\Notifications\*::toDatabase()) — rather than adding
     * a broadcast channel to each one individually, this fires a single
     * generic push the moment any of them lands a row, so real-time
     * delivery is automatic for every current and future notification
     * without touching the notification classes themselves.
     */
    protected function configureNotificationBroadcast(): void
    {
        DatabaseNotification::created(function (DatabaseNotification $notification) {
            if ($notification->notifiable_type === User::class) {
                event(new NotificationCreated($notification));
            }
        });
    }
}

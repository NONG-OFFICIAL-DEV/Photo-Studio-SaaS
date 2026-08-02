<?php

namespace App\Services;

use App\Actions\ProvisionTenantRolesAction;
use App\DTO\RegisterTenantData;
use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\NewTenantRegisteredNotification;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected TenantRepositoryInterface $tenants,
        protected ProvisionTenantRolesAction $provisionTenantRoles,
        protected SecurityEventLogger $securityEvents,
    ) {
    }

    /**
     * Creates a new tenant (photography studio) with its Owner user,
     * a 14-day trial subscription, and the tenant's own copy of the
     * baseline RBAC roles.
     */
    public function register(RegisterTenantData $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = $this->tenants->create([
                'name' => $data->studioName,
                'slug' => $data->slug,
                'email' => $data->email,
                'phone' => $data->phone,
            ]);

            $plan = $data->planCode
                ? Plan::where('code', $data->planCode)->firstOrFail()
                : Plan::where('code', 'free_trial')->firstOrFail();

            $trialDays = max($plan->trial_days, 1);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'billing_cycle' => BillingCycle::Monthly,
                'trial_ends_at' => now()->addDays($trialDays),
                'current_period_start' => now(),
                'current_period_ends_at' => now()->addDays($trialDays),
            ]);

            $this->provisionTenantRoles->execute($tenant);

            /** @var User $user */
            $user = $this->users->create([
                'tenant_id' => $tenant->id,
                'name' => $data->ownerName,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => Hash::make($data->password),
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->assignRole(TenantRole::Owner->value);

            event(new Registered($user));

            $superAdmins = User::query()->where('is_super_admin', true)->get();
            if ($superAdmins->isNotEmpty()) {
                Notification::send($superAdmins, new NewTenantRegisteredNotification($tenant, $plan->name));
            }

            $token = JWTAuth::fromUser($user);

            return $this->tokenPayload($user->fresh(), $token);
        });
    }

    public function login(string $email, string $password, bool $remember = false): array
    {
        $factory = auth('api')->factory();
        $originalTtl = $factory->getTTL();

        if ($remember) {
            $factory->setTTL((int) config('jwt.refresh_ttl'));
        }

        $token = auth('api')->claims(['remember' => $remember])
            ->attempt(['email' => $email, 'password' => $password]);

        $ttlMinutes = $factory->getTTL();
        $factory->setTTL($originalTtl);

        if (! $token) {
            $this->securityEvents->loginAttempt($email, User::where('email', $email)->first(), false, 'Invalid credentials', request());
            throw new InvalidCredentialsException;
        }

        /** @var User $user */
        $user = auth('api')->user();

        if (! $user->isActive()) {
            auth('api')->logout();
            $this->securityEvents->loginAttempt($email, $user, false, 'Account deactivated', request());
            throw new InvalidCredentialsException('This account has been deactivated.', 'ACCOUNT_DEACTIVATED');
        }

        $this->securityEvents->loginAttempt($email, $user, true, null, request());

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        return $this->tokenPayload($user, $token, $ttlMinutes);
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    /**
     * Refreshing a token normally re-issues it with the default TTL, which
     * would silently downgrade a "remember me" session back to a short
     * lifetime on the very first background refresh. Re-apply the extended
     * TTL here whenever the token being refreshed carries the `remember`
     * claim (persisted across refreshes via config('jwt.persistent_claims')).
     */
    public function refresh(): array
    {
        $remember = (bool) auth('api')->payload()->get('remember', false);

        $factory = auth('api')->factory();
        $originalTtl = $factory->getTTL();

        if ($remember) {
            $factory->setTTL((int) config('jwt.refresh_ttl'));
        }

        $token = auth('api')->refresh();

        $ttlMinutes = $factory->getTTL();
        $factory->setTTL($originalTtl);

        /** @var User $user */
        $user = auth('api')->user();

        // Re-checked here, not just at login — an access token stays valid
        // for its full TTL regardless of what happens to the account
        // afterward, so deactivating someone mid-session would otherwise
        // let them keep refreshing into new tokens for up to
        // jwt.refresh_ttl (default 14 days) before it actually takes
        // effect. This caps that gap to at most one access-token TTL.
        if (! $user->isActive()) {
            auth('api')->logout();
            throw new InvalidCredentialsException('This account has been deactivated.', 'ACCOUNT_DEACTIVATED');
        }

        return $this->tokenPayload($user, $token, $ttlMinutes);
    }

    public function me(): User
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $user->load('tenant');
    }

    public function sendPasswordResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(string $email, string $token, string $password): string
    {
        return Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );
    }

    protected function tokenPayload(User $user, string $token, ?int $ttlMinutes = null): array
    {
        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => ($ttlMinutes ?? auth('api')->factory()->getTTL()) * 60,
        ];
    }
}

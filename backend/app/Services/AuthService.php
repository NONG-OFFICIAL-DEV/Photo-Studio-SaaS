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
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected TenantRepositoryInterface $tenants,
        protected ProvisionTenantRolesAction $provisionTenantRoles,
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

        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);

        $factory->setTTL($originalTtl);

        if (! $token) {
            throw new InvalidCredentialsException;
        }

        /** @var User $user */
        $user = auth('api')->user();

        if (! $user->isActive()) {
            auth('api')->logout();
            throw new InvalidCredentialsException('This account has been deactivated.');
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        return $this->tokenPayload($user, $token);
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refresh(): array
    {
        $token = auth('api')->refresh();

        /** @var User $user */
        $user = auth('api')->user();

        return $this->tokenPayload($user, $token);
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

    protected function tokenPayload(User $user, string $token): array
    {
        return [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }
}

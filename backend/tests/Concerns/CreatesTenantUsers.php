<?php

namespace Tests\Concerns;

use App\Actions\ProvisionTenantRolesAction;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Test fixture builder for "a tenant with a user in role X, with an active
 * subscription" — every module's authenticated feature tests need this.
 * Provisions roles via the real ProvisionTenantRolesAction so tests run
 * against the same permission wiring production tenants get.
 */
trait CreatesTenantUsers
{
    protected function createTenantWithUser(TenantRole $role = TenantRole::Owner, array $userAttributes = []): array
    {
        $tenant = Tenant::factory()->create();

        app(ProvisionTenantRolesAction::class)->execute($tenant);

        $plan = Plan::firstOrCreate(
            ['code' => 'test_plan'],
            ['name' => 'Test Plan', 'trial_days' => 14, 'is_active' => true]
        );

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        /** @var User $user */
        $user = User::factory()->create([...$userAttributes, 'tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return [$tenant, $user];
    }

    /**
     * Mints a token via the JWT manager directly (`tymon.jwt`), NOT via
     * auth('api')->fromUser(). Going through the guard has a side effect:
     * AuthManager::guard('api') lazily constructs and caches a JWTGuard
     * bound to whatever request happens to be in the container *right
     * now* (Tymon\JWTAuth\Providers\AbstractServiceProvider registers a
     * container "rebind" callback to keep that guard's request in sync,
     * but in back-to-back simulated HTTP calls within one test method that
     * update can land after the guard has already resolved-and-cached a
     * user for the wrong request). Minting the token through the JWT
     * manager directly never touches the guard, so it can't leave stale
     * guard state behind for the next request.
     */
    protected function tokenFor(User $user): string
    {
        return app('tymon.jwt')->fromUser($user);
    }

    /**
     * forgetGuards() additionally clears any guard a *previous* request's
     * real middleware pipeline resolved-and-cached — without it, that
     * cached user (not this call's $user) would answer the next request
     * regardless of which token is on it. The guard instance and its
     * cached user persist across simulated HTTP calls within one test
     * method. See TenantIsolationTest for the same issue at the app-code
     * level.
     */
    protected function authHeader(User $user): array
    {
        auth()->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->tokenFor($user)];
    }
}

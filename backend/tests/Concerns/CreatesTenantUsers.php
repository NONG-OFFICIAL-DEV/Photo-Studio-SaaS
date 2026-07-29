<?php

namespace Tests\Concerns;

use App\Actions\ProvisionTenantRolesAction;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
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
     * Adds a second (third, ...) user with a given role to an *existing*
     * tenant — e.g. an Owner plus a Photographer in the same tenant, to
     * test staff-assignment / conflict-detection scenarios. Roles must
     * already be provisioned for $tenant (true for any tenant returned by
     * createTenantWithUser()).
     */
    protected function addUserToTenant(Tenant $tenant, TenantRole $role, array $userAttributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create([...$userAttributes, 'tenant_id' => $tenant->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Use this instead of hand-rolling an Authorization header. A real JWT
     * round-trip (mint a token, send it, have the guard parse it back) is
     * already covered by the Auth module's own tests (LoginTest et al) —
     * every other module just needs "act as this tenant user" as a fixture.
     *
     * That matters here specifically because Tymon's JWTGuard/JWT/Parser
     * are container singletons that cache internal state (resolved user,
     * parsed token) with no built-in per-request reset — harmless in
     * production, where php-fpm tears the container down between real
     * requests, but it means two hand-crafted bearer tokens for two
     * different users, sent as separate simulated requests within one test
     * method, do NOT reliably re-authenticate as their own user. Laravel's
     * actingAs() sidesteps all of it by calling setUser() directly on the
     * guard, so each call — even for a different user — takes effect
     * immediately and correctly for the next request.
     *
     * TenantContext (also a container singleton, see App\Services\
     * TenantContext) has the same class of issue: it's only ever SET by
     * IdentifyTenant, never cleared, so a route that skips that middleware
     * (e.g. the Super Admin panel's /admin/* routes) would otherwise
     * inherit whatever tenant a PRIOR simulated request in the same test
     * left behind — invisible in production (fresh container per request)
     * but very real here, since one test method's requests all share one
     * container. Clearing it here means every actingAsUser() call starts
     * from the same blank slate a real request would.
     */
    protected function actingAsUser(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user, 'api');
    }
}

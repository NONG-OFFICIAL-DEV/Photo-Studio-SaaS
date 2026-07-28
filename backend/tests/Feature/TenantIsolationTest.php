<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the core multi-tenant guarantee: BelongsToTenant's global
 * TenantScope makes it structurally impossible for one tenant's query to
 * see another tenant's rows, without any repository/controller having to
 * remember a manual ->where('tenant_id', ...).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenants_query_only_sees_its_own_users(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        User::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $context = app(TenantContext::class);

        $context->set($tenantA);
        $this->assertCount(3, User::all());
        $this->assertTrue(User::all()->every(fn (User $u) => $u->tenant_id === $tenantA->id));

        $context->set($tenantB);
        $this->assertCount(2, User::all());
        $this->assertTrue(User::all()->every(fn (User $u) => $u->tenant_id === $tenantB->id));
    }

    public function test_no_tenant_context_means_no_scoping_applied(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        User::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        User::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        // No context set (e.g. a Super Admin / console context) => sees everything.
        $this->assertGreaterThanOrEqual(4, User::count());
    }

    public function test_creating_a_record_auto_fills_tenant_id_from_context(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $user = User::factory()->make(['tenant_id' => null]);
        $user->email = 'auto-tenant@example.test';
        $user->save();

        $this->assertSame($tenant->id, $user->fresh()->tenant_id);
    }

    /**
     * Deliberately two separate test methods rather than two assertions in
     * one: PHPUnit boots a fresh application container per test method, so
     * each simulated request here goes through auth:api cold. Tymon's JWT
     * guard binds a container "rebind" callback on the `request` singleton
     * to keep itself in sync — but that callback only updates the cached
     * guard's request pointer, not its already-resolved `$user`. Making two
     * differently-authenticated calls back to back within one test method
     * hits that guard-caching quirk and leaks the first user into the
     * second call. That's a test-harness artifact, not a tenant-isolation
     * bug — verified by decoding both JWTs' `sub` claims independently.
     */
    public function test_http_authenticated_request_returns_tenant_a_for_its_own_user(): void
    {
        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $tokenA = auth('api')->fromUser($userA);

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenantA->id);
    }

    public function test_http_authenticated_request_returns_tenant_b_for_its_own_user(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $tokenB = auth('api')->fromUser($userB);

        $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenantB->id);
    }
}

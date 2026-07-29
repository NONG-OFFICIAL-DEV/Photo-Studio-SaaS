<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminSubscriptionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_admin_can_change_a_tenants_plan(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $newPlan = Plan::factory()->create(['name' => 'Enterprise']);

        $response = $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/subscription/plan", ['plan_id' => $newPlan->id])
            ->assertOk();

        $response->assertJsonPath('data.plan.id', $newPlan->id);
    }

    public function test_admin_cannot_switch_a_tenant_to_a_free_plan_either(): void
    {
        // Free Trial is a one-time onboarding plan assigned only at
        // registration — nobody, not even an admin override, can put a
        // subscription back onto a plan with no real price.
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $freePlan = Plan::factory()->create(['price_monthly' => 0, 'price_quarterly' => null, 'price_yearly' => null]);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/subscription/plan", ['plan_id' => $freePlan->id])
            ->assertStatus(422);
    }

    public function test_admin_can_renew_a_tenants_subscription_and_it_is_recorded_as_admin_action(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_yearly' => 290]);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/renew", ['billing_cycle' => 'yearly'])
            ->assertOk()
            ->assertJsonPath('data.billing_cycle', 'yearly');

        $this->assertDatabaseHas('subscription_payments', ['tenant_id' => $tenant->id, 'recorded_by' => $superAdmin->id, 'amount' => 290]);
    }

    public function test_admin_cannot_renew_a_free_plan_either(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/renew")
            ->assertStatus(422);
    }

    public function test_admin_can_cancel_and_resume_a_tenants_subscription(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/resume")
            ->assertOk();

        $tenant->refresh();
        $this->assertNull($tenant->activeSubscription->cancelled_at);
    }

    public function test_admin_can_suspend_and_reactivate_a_subscription(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        // A suspended subscription blocks the tenant even though is_active (tenant-level) is untouched.
        $this->assertTrue($tenant->fresh()->is_active);
        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertStatus(402);

        $response = $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/reactivate")
            ->assertOk();

        // Reactivating recomputes from dates — current_period_ends_at is still in the future, so it comes back as Active.
        $response->assertJsonPath('data.status', 'active');

        // Refresh: the prior request already cached $owner's stale (Suspended)
        // tenant->activeSubscription relation in-memory — a fresh model
        // instance is what a real subsequent request would use.
        $this->actingAsUser($owner->fresh())->getJson('/api/v1/customers')->assertOk();
    }

    public function test_reactivate_only_works_on_a_suspended_subscription(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/reactivate")
            ->assertStatus(422);
    }

    public function test_admin_can_view_a_tenants_payment_history(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29]);

        $this->actingAsUser($superAdmin)->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/renew")->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/subscription/payments")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_a_regular_tenant_user_cannot_access_admin_subscription_routes(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/renew")
            ->assertForbidden();
    }
}

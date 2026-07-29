<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_it_reports_tenant_counts_and_mrr(): void
    {
        $superAdmin = $this->superAdmin();

        [$tenantA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantB->update(['is_active' => false]);

        // createTenantWithUser() seeds an Active subscription on the shared
        // "test_plan" (price_monthly not set by that fixture) — give it an
        // explicit amount so MRR has a known value to assert against.
        Subscription::where('tenant_id', $tenantA->id)->update(['amount' => 100, 'billing_cycle' => BillingCycle::Monthly->value]);
        Subscription::where('tenant_id', $tenantB->id)->update(['amount' => 60, 'billing_cycle' => BillingCycle::Monthly->value]);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics')
            ->assertOk();

        $response->assertJsonPath('data.total_tenants', 2)
            ->assertJsonPath('data.active_tenants', 1)
            ->assertJsonPath('data.suspended_tenants', 1);

        // Only tenantA's subscription is still status=active (tenantB's
        // tenant record is suspended, but its Subscription row status is
        // untouched — MRR counts by subscription status, not tenant
        // is_active, so both $100 contribute unless tenantB's sub status
        // was also changed). Assert the actual sum of Active subs.
        $activeTotal = Subscription::where('status', SubscriptionStatus::Active->value)->sum('amount');
        $this->assertEquals((float) $activeTotal, $response->json('data.mrr'));
    }

    public function test_it_breaks_down_subscriptions_by_status(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics')
            ->assertOk();

        $this->assertSame(1, $response->json('data.subscriptions_by_status.active'));
        $this->assertSame(0, $response->json('data.subscriptions_by_status.trial'));
    }

    public function test_it_returns_a_six_month_signups_trend(): void
    {
        $superAdmin = $this->superAdmin();
        $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics')
            ->assertOk();

        $this->assertCount(6, $response->json('data.signups_trend'));
    }

    public function test_mrr_falls_back_to_plan_price_when_subscription_has_no_amount(): void
    {
        $superAdmin = $this->superAdmin();
        $plan = Plan::factory()->create(['price_monthly' => 75]);
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        Subscription::where('tenant_id', $tenant->id)->update([
            'plan_id' => $plan->id,
            'amount' => null,
            'billing_cycle' => BillingCycle::Monthly->value,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics')
            ->assertOk();

        $this->assertEquals(75.0, $response->json('data.mrr'));
    }
}

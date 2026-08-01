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

    public function test_new_tenants_and_revenue_collected_respect_the_selected_date_range(): void
    {
        $superAdmin = $this->superAdmin();

        // created_at isn't in Tenant::$fillable, so a mass-assigned update()
        // would silently no-op — set it directly and save() to bypass that.
        [$tenantOld] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantOld->created_at = now()->subMonths(3);
        $tenantOld->save();

        [$tenantNew] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantNew->created_at = now()->subDays(2);
        $tenantNew->save();

        \App\Models\SubscriptionPayment::create([
            'tenant_id' => $tenantNew->id,
            'subscription_id' => Subscription::where('tenant_id', $tenantNew->id)->value('id'),
            'plan_id' => Subscription::where('tenant_id', $tenantNew->id)->value('plan_id'),
            'amount' => 49,
            'billing_cycle' => BillingCycle::Monthly->value,
            'period_start' => now()->subDays(1),
            'period_end' => now()->addMonth(),
            'paid_at' => now()->subDays(1),
        ]);
        \App\Models\SubscriptionPayment::create([
            'tenant_id' => $tenantOld->id,
            'subscription_id' => Subscription::where('tenant_id', $tenantOld->id)->value('id'),
            'plan_id' => Subscription::where('tenant_id', $tenantOld->id)->value('plan_id'),
            'amount' => 999,
            'billing_cycle' => BillingCycle::Monthly->value,
            'period_start' => now()->subMonths(3),
            'period_end' => now()->subMonths(2),
            'paid_at' => now()->subMonths(3),
        ]);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics?date_from='.now()->subWeek()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk();

        $response->assertJsonPath('data.new_tenants', 1);
        $this->assertEquals(49.0, $response->json('data.revenue_collected'));

        // Both tenants still count toward the always-current totals,
        // regardless of the date filter applied above.
        $response->assertJsonPath('data.total_tenants', 2);
    }

    public function test_signups_trend_spans_the_requested_range_not_a_fixed_six_months(): void
    {
        $superAdmin = $this->superAdmin();
        $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics?date_from='.now()->subDays(3)->toDateString().'&date_to='.now()->toDateString())
            ->assertOk();

        // A 4-day range groups by day, not month — expect 4 daily points.
        $this->assertCount(4, $response->json('data.signups_trend'));
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

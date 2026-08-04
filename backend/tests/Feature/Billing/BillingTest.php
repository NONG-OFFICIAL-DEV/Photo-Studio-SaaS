<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_view_their_billing_status(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/billing')
            ->assertOk();

        $response->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.plan.code', 'test_plan')
            ->assertJsonStructure(['data' => ['subscription' => ['id', 'is_usable'], 'usage' => ['users_count', 'orders_this_month_count'], 'payment_info' => ['khqr_image_url', 'bank_name', 'bank_account_name', 'bank_account_number', 'payment_instructions']]]);
    }

    public function test_billing_includes_the_platforms_payment_details(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        PlatformSetting::current()->update(['bank_name' => 'ABA Bank', 'bank_account_number' => '000123456']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/billing')
            ->assertOk()
            ->assertJsonPath('data.payment_info.bank_name', 'ABA Bank')
            ->assertJsonPath('data.payment_info.bank_account_number', '000123456');
    }

    public function test_a_manager_cannot_view_billing(): void
    {
        [, $manager] = $this->createTenantWithUser(TenantRole::Manager);

        $this->actingAsUser($manager)
            ->getJson('/api/v1/billing')
            ->assertForbidden();
    }

    public function test_billing_is_reachable_even_when_the_subscription_has_expired(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update(['status' => SubscriptionStatus::Expired]);

        // A regular tenant route is correctly blocked...
        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertStatus(402);

        // ...but billing must stay reachable so the tenant can fix it themselves.
        $this->actingAsUser($owner)->getJson('/api/v1/billing')->assertOk();
    }

    public function test_it_lists_active_plans_to_choose_from(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Plan::factory()->create(['name' => 'Pro', 'is_active' => true]);
        Plan::factory()->create(['name' => 'Hidden', 'is_active' => false]);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Pro'));
        $this->assertFalse($names->contains('Hidden'));
    }

    public function test_the_plan_picker_excludes_free_plans(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Plan::factory()->create(['name' => 'Free Trial', 'price_monthly' => 0, 'price_quarterly' => null, 'price_yearly' => null]);
        Plan::factory()->create(['name' => 'Starter', 'price_monthly' => 19]);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertFalse($names->contains('Free Trial'));
        $this->assertTrue($names->contains('Starter'));
    }

    public function test_a_tenant_cannot_self_service_switch_back_to_a_free_plan(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $freePlan = Plan::factory()->create(['price_monthly' => 0, 'price_quarterly' => null, 'price_yearly' => null]);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $freePlan->id])
            ->assertStatus(422);
    }

    public function test_it_renews_and_records_a_payment(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/renew', ['billing_cycle' => 'monthly'])
            ->assertOk();

        $response->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.billing_cycle', 'monthly')
            ->assertJsonPath('data.amount', 29);

        $this->assertDatabaseCount('subscription_payments', 1);
        $this->assertDatabaseHas('subscription_payments', ['tenant_id' => $tenant->id, 'recorded_by' => null, 'amount' => 29]);
    }

    public function test_it_cannot_renew_a_free_plan(): void
    {
        // The fixture's default "test_plan" has price_monthly=0 (the
        // migration's column default) — same shape as the real seeded
        // Free Trial plan, so this is exactly the "can Free Trial renew?"
        // case: it must not, or a tenant could stay on a $0 plan forever.
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/renew', ['billing_cycle' => 'monthly'])
            ->assertStatus(422);

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_it_cannot_renew_on_a_billing_cycle_the_plan_does_not_offer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29, 'price_yearly' => null]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/renew', ['billing_cycle' => 'yearly'])
            ->assertStatus(422);
    }

    public function test_renew_extends_from_the_existing_period_end_not_from_now(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29]);
        $futureEnd = now()->addDays(10);
        $tenant->activeSubscription->update([
            'status' => \App\Enums\SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_ends_at' => $futureEnd,
        ]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/renew', ['billing_cycle' => 'monthly'])
            ->assertOk();

        $newEnd = \Carbon\Carbon::parse($response->json('data.current_period_ends_at'));
        $this->assertEquals($futureEnd->copy()->addMonth()->toDateString(), $newEnd->toDateString());
    }

    public function test_it_changes_plan_without_charging(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $newPlan = Plan::factory()->create(['name' => 'Studio Pro']);

        $response = $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $newPlan->id])
            ->assertOk();

        $response->assertJsonPath('data.plan.id', $newPlan->id);
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_changing_plan_can_also_switch_billing_cycle_in_the_same_request(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update(['billing_cycle' => 'monthly']);
        $newPlan = Plan::factory()->create(['name' => 'Studio Pro', 'price_yearly' => 290]);

        $response = $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $newPlan->id, 'billing_cycle' => 'yearly'])
            ->assertOk();

        $response->assertJsonPath('data.plan.id', $newPlan->id)
            ->assertJsonPath('data.billing_cycle', 'yearly')
            ->assertJsonPath('data.amount', 290);
    }

    public function test_changing_plan_without_a_cycle_keeps_the_existing_one(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update(['billing_cycle' => 'quarterly']);
        $newPlan = Plan::factory()->create(['name' => 'Studio Pro', 'price_quarterly' => 79]);

        $response = $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $newPlan->id])
            ->assertOk();

        $response->assertJsonPath('data.billing_cycle', 'quarterly')
            ->assertJsonPath('data.amount', 79);
    }

    public function test_it_cannot_change_plan_onto_a_billing_cycle_the_new_plan_does_not_offer(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $newPlan = Plan::factory()->create(['price_yearly' => null]);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $newPlan->id, 'billing_cycle' => 'yearly'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BILLING_CYCLE_NOT_AVAILABLE');
    }

    public function test_it_cannot_change_to_an_inactive_plan(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $inactivePlan = Plan::factory()->create(['is_active' => false]);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/billing/plan', ['plan_id' => $inactivePlan->id])
            ->assertStatus(422);
    }

    public function test_it_cancels_at_period_end_and_can_be_resumed(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $cancelResponse = $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/cancel')
            ->assertOk();

        $this->assertNotNull($cancelResponse->json('data.cancelled_at'));
        // Cancelling at period end doesn't revoke access immediately.
        $this->assertTrue($cancelResponse->json('data.is_usable'));

        $this->actingAsUser($owner)->postJson('/api/v1/billing/cancel')->assertStatus(422);

        $resumeResponse = $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/resume')
            ->assertOk();

        $this->assertNull($resumeResponse->json('data.cancelled_at'));
    }

    public function test_it_cannot_resume_a_subscription_that_was_never_cancelled(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/billing/resume')
            ->assertStatus(422);
    }

    public function test_payment_history_is_isolated_per_tenant(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantA->activeSubscription->plan->update(['price_monthly' => 29]);

        $this->actingAsUser($ownerA)->postJson('/api/v1/billing/renew')->assertOk();

        $response = $this->actingAsUser($ownerB)
            ->getJson('/api/v1/billing/payments')
            ->assertOk();

        $this->assertEmpty($response->json('data'));
    }
}

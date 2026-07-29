<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ExpireSubscriptionsCommandTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_expires_a_trial_past_its_end_date(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update([
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Expired, $tenant->activeSubscription->fresh()->status);
    }

    public function test_it_expires_an_active_subscription_past_its_period_end(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update(['current_period_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Expired, $tenant->activeSubscription->fresh()->status);
    }

    public function test_a_subscription_with_a_pending_cancellation_becomes_cancelled_not_expired(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update([
            'current_period_ends_at' => now()->subDay(),
            'cancelled_at' => now()->subDays(2),
        ]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Cancelled, $tenant->activeSubscription->fresh()->status);
    }

    public function test_it_leaves_subscriptions_that_are_not_yet_due_untouched(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        // Fixture's default current_period_ends_at is a month in the future.

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Active, $tenant->activeSubscription->fresh()->status);
    }

    public function test_it_sweeps_across_every_tenant_in_one_pass(): void
    {
        [$tenantA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantA->activeSubscription->update(['current_period_ends_at' => now()->subDay()]);
        $tenantB->activeSubscription->update(['current_period_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Expired, $tenantA->activeSubscription->fresh()->status);
        $this->assertSame(SubscriptionStatus::Expired, $tenantB->activeSubscription->fresh()->status);
    }
}

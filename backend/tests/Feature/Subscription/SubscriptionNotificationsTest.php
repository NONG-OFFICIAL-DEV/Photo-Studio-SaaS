<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class SubscriptionNotificationsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_owner_is_notified_when_their_subscription_is_suspended(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = $this->superAdmin();

        app(SubscriptionService::class)->suspend($tenant->activeSubscription, $superAdmin);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('subscription.suspended', $owner->notifications()->first()->data['event']);
        // Super admin performed the action themselves — no self-notification.
        $this->assertSame(0, $superAdmin->notifications()->count());
    }

    public function test_owner_is_notified_when_their_subscription_is_reactivated(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = $this->superAdmin();
        $subscriptions = app(SubscriptionService::class);

        $subscriptions->suspend($tenant->activeSubscription, $superAdmin);
        $subscriptions->reactivate($tenant->activeSubscription->fresh(), $superAdmin);

        $events = $owner->notifications()->pluck('data')->pluck('event')->all();
        $this->assertContains('subscription.reactivated', $events);
    }

    public function test_owner_is_notified_when_their_subscription_is_renewed(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29.99]);

        app(SubscriptionService::class)->renew($tenant->activeSubscription, null, $owner);

        $this->assertSame(1, $owner->notifications()->count());
        $data = $owner->notifications()->first()->data;
        $this->assertSame('subscription.renewed', $data['event']);
    }

    public function test_owner_and_super_admins_are_notified_when_a_subscription_expires(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = $this->superAdmin();
        $tenant->activeSubscription->update(['current_period_ends_at' => now()->subDay()]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('subscription.expired', $owner->notifications()->first()->data['event']);
        $this->assertSame(1, $superAdmin->notifications()->count());
        $this->assertSame('subscription.expired', $superAdmin->notifications()->first()->data['event']);
    }

    public function test_a_deliberately_cancelled_subscription_lapsing_does_not_notify(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->superAdmin();
        $tenant->activeSubscription->update([
            'current_period_ends_at' => now()->subDay(),
            'cancelled_at' => now()->subDays(2),
        ]);

        $this->artisan('subscriptions:expire')->assertExitCode(0);

        $this->assertSame(SubscriptionStatus::Cancelled, $tenant->activeSubscription->fresh()->status);
        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_notify_expiring_warns_owner_and_super_admins_once_within_the_threshold(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = $this->superAdmin();
        $tenant->activeSubscription->update(['current_period_ends_at' => now()->addDays(2)]);

        $this->artisan('subscriptions:notify-expiring')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('subscription.expiring_soon', $owner->notifications()->first()->data['event']);
        $this->assertSame(1, $superAdmin->notifications()->count());
        $this->assertNotNull($tenant->activeSubscription->fresh()->expiring_soon_notified_at);
    }

    public function test_notify_expiring_does_not_repeat_within_the_same_period(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->update(['current_period_ends_at' => now()->addDays(2)]);

        $this->artisan('subscriptions:notify-expiring')->assertExitCode(0);
        $this->artisan('subscriptions:notify-expiring')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_notify_expiring_ignores_subscriptions_outside_the_threshold(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        // Fixture default current_period_ends_at is a month out.
        $this->assertTrue($tenant->activeSubscription->current_period_ends_at->isAfter(now()->addDays(3)));

        $this->artisan('subscriptions:notify-expiring')->assertExitCode(0);

        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_renewing_resets_the_expiring_soon_flag_for_the_new_period(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['price_monthly' => 29.99]);
        $tenant->activeSubscription->update([
            'current_period_ends_at' => now()->addDays(2),
            'expiring_soon_notified_at' => now()->subDay(),
        ]);

        app(SubscriptionService::class)->renew($tenant->activeSubscription, null, $owner);

        $this->assertNull($tenant->activeSubscription->fresh()->expiring_soon_notified_at);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\User;
use App\Notifications\Billing\SubscriptionSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_lists_the_authenticated_users_own_notifications(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'subscription.suspended')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_a_user_never_sees_another_users_notifications(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($ownerA, new SubscriptionSuspendedNotification($tenantA->activeSubscription, null));
        NotificationFacade::send($ownerB, new SubscriptionSuspendedNotification($tenantB->activeSubscription, null));

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unread_count_endpoint(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));

        $this->actingAsUser($owner)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_it_marks_a_single_notification_as_read(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAsUser($owner)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($ownerA, new SubscriptionSuspendedNotification($tenantA->activeSubscription, null));
        $notification = $ownerA->notifications()->firstOrFail();

        $this->actingAsUser($ownerB)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_it_marks_every_unread_notification_as_read(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));

        $this->actingAsUser($owner)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_notifications_are_reachable_even_when_the_subscription_has_expired(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        NotificationFacade::send($owner, new SubscriptionSuspendedNotification($tenant->activeSubscription, null));
        $tenant->activeSubscription->update(['status' => SubscriptionStatus::Expired]);

        // A regular tenant route is correctly blocked...
        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertStatus(402);

        // ...but notifications must stay reachable, so a blocked tenant can
        // still see (and understand) the notification explaining why.
        $this->actingAsUser($owner)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_super_admin_can_use_the_same_endpoints(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

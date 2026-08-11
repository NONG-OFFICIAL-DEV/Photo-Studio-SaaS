<?php

namespace Tests\Feature;

use App\Enums\TenantRole;
use App\Events\NotificationCreated;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function makeNotificationRow(string $notifiableType, string $notifiableId): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'data' => ['event' => 'test.event', 'severity' => 'info'],
            'read_at' => null,
        ]);
    }

    public function test_creating_a_user_notification_row_broadcasts_it(): void
    {
        Event::fake([NotificationCreated::class]);
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $row = $this->makeNotificationRow(User::class, $owner->id);

        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event) => $event->notification->id === $row->id
        );
    }

    public function test_the_broadcast_channel_and_payload_are_correct(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $row = $this->makeNotificationRow(User::class, $owner->id);

        $event = new NotificationCreated($row);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame("private-App.Models.User.{$owner->id}", $channels[0]->name);
        $this->assertSame('notification.created', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($row->id, $payload['id']);
        $this->assertSame('test.event', $payload['event']);
    }

    public function test_a_non_user_notification_row_does_not_broadcast(): void
    {
        Event::fake([NotificationCreated::class]);
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->makeNotificationRow(Customer::class, $customer->id);

        Event::assertNotDispatched(NotificationCreated::class);
    }

    /**
     * Regression test for a real bug: routes/channels.php was scaffolded
     * by `reverb:install` as `(int) $user->id === (int) $id` — every PK in
     * this app is a UUID string, so that cast collapsed both sides to 0
     * and authorized ANY authenticated user for ANY other user's private
     * notification channel.
     */
    public function test_a_user_can_only_authorize_their_own_notification_channel(): void
    {
        // phpunit.xml forces BROADCAST_CONNECTION=null so unrelated tests don't
        // need a live broadcaster; the null driver no-ops auth checks entirely.
        // Broadcast::channel() registers against whichever driver was active
        // at boot time (null), so switching the default here alone gets a
        // fresh 'reverb' broadcaster with no channels registered at all —
        // re-requiring channels.php registers the real definition on it too.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        [, $otherOwner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-App.Models.User.{$owner->id}",
            ])
            ->assertOk();

        $this->actingAsUser($owner)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-App.Models.User.{$otherOwner->id}",
            ])
            ->assertStatus(403);
    }

    public function test_broadcasting_auth_requires_authentication(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$owner->id}",
        ])->assertStatus(401);
    }
}

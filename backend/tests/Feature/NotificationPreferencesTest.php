<?php

namespace Tests\Feature;

use App\Enums\TenantRole;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Billing\SubscriptionSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function configurePlatformBot(): void
    {
        config([
            'services.platform_telegram.bot_token' => 'test-platform-token',
            'services.platform_telegram.bot_username' => 'PhotoStudioAdminBot',
            'services.platform_telegram.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_it_shows_default_preferences_for_a_new_user(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->getJson('/api/v1/notifications/preferences')->assertOk();

        $response->assertJsonPath('data.channels.mail', true)
            ->assertJsonPath('data.channels.system', true)
            ->assertJsonPath('data.channels.telegram', false)
            ->assertJsonPath('data.telegram.linked', false);
    }

    public function test_it_updates_notification_channel_preferences(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/notifications/preferences', ['mail' => false, 'system' => true, 'telegram' => false])
            ->assertOk();

        $prefs = $owner->fresh()->notificationChannelPreferences();
        $this->assertFalse($prefs['mail']);
        $this->assertTrue($prefs['system']);
    }

    public function test_enabling_telegram_without_a_linked_account_is_rejected(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/notifications/preferences', ['mail' => true, 'system' => true, 'telegram' => true])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.telegram.0', 'Connect your Telegram account before enabling this channel.');
    }

    public function test_linking_telegram_requires_the_platform_bot_to_be_configured(): void
    {
        config(['services.platform_telegram.bot_token' => null, 'services.platform_telegram.bot_username' => null]);
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/notifications/telegram/link')
            ->assertStatus(422)
            ->assertJsonPath('code', 'PLATFORM_TELEGRAM_NOT_CONFIGURED');
    }

    public function test_it_generates_a_telegram_deep_link(): void
    {
        $this->configurePlatformBot();
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/notifications/telegram/link')->assertOk();

        $token = $owner->fresh()->telegram_link_token;
        $this->assertNotNull($token);
        $response->assertJsonPath('data.linked', false)
            ->assertJsonPath('data.link', "https://t.me/PhotoStudioAdminBot?start={$token}");
    }

    public function test_the_webhook_links_a_pending_token_to_a_real_chat_id(): void
    {
        $this->configurePlatformBot();
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true])]);
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $owner->update(['telegram_link_token' => 'pending-token-123']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-webhook-secret')
            ->postJson('/api/v1/webhooks/telegram-platform', [
                'message' => ['text' => '/start pending-token-123', 'chat' => ['id' => 987654321]],
            ])->assertOk();

        $owner->refresh();
        $this->assertSame('987654321', $owner->telegram_chat_id);
        $this->assertNotNull($owner->telegram_linked_at);
        $this->assertTrue($owner->notificationChannelPreferences()['telegram']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottest-platform-token/sendMessage'
            && $request['chat_id'] === '987654321');
    }

    public function test_the_webhook_rejects_requests_with_the_wrong_secret(): void
    {
        $this->configurePlatformBot();
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $owner->update(['telegram_link_token' => 'pending-token-456']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson('/api/v1/webhooks/telegram-platform', [
                'message' => ['text' => '/start pending-token-456', 'chat' => ['id' => 111]],
            ])->assertOk();

        $this->assertNull($owner->fresh()->telegram_chat_id);
    }

    public function test_unlinking_telegram_clears_the_chat_and_disables_the_channel(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $owner->update([
            'telegram_chat_id' => '555',
            'telegram_linked_at' => now(),
            'notification_channels' => ['mail' => true, 'system' => true, 'telegram' => true],
        ]);

        $this->actingAsUser($owner)->postJson('/api/v1/notifications/telegram/unlink')->assertOk();

        $owner->refresh();
        $this->assertNull($owner->telegram_chat_id);
        $this->assertFalse($owner->notificationChannelPreferences()['telegram']);
    }

    public function test_a_notification_is_only_delivered_via_channels_the_user_has_enabled(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $owner->update(['notification_channels' => ['mail' => false, 'system' => true, 'telegram' => false]]);

        $notification = new SubscriptionSuspendedNotification($tenant->activeSubscription, null);

        $channels = $notification->via($owner->fresh());

        $this->assertSame(['database'], $channels);
    }

    public function test_a_notification_includes_telegram_only_when_enabled_and_linked(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $owner->update([
            'telegram_chat_id' => '999',
            'notification_channels' => ['mail' => false, 'system' => false, 'telegram' => true],
        ]);

        $notification = new SubscriptionSuspendedNotification($tenant->activeSubscription, null);

        $channels = $notification->via($owner->fresh());

        $this->assertSame(['App\Notifications\Channels\TelegramAdminChannel'], $channels);
    }

    public function test_telegram_preference_alone_without_a_linked_chat_does_not_enable_the_channel(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        // Preference says "on" but no chat_id was ever actually linked —
        // shouldn't happen via the API (update() blocks this), but the
        // model-level guard must hold regardless of how the flag got set.
        $owner->update(['notification_channels' => ['mail' => false, 'system' => false, 'telegram' => true]]);

        $notification = new SubscriptionSuspendedNotification($tenant->activeSubscription, null);

        $this->assertSame([], $notification->via($owner->fresh()));
    }
}

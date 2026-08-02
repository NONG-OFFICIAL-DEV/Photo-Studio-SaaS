<?php

namespace Tests\Feature\Telegram;

use App\Enums\TenantRole;
use App\Models\Album;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function fakeSuccessfulConnect(): void
    {
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'my_studio_bot']]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    public function test_owner_can_connect_a_telegram_bot(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->fakeSuccessfulConnect();

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/settings/telegram/connect', ['bot_token' => '123:ABC'])
            ->assertOk();

        $response->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.bot_username', 'my_studio_bot');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
            && $request['url'] === route('api.v1.webhooks.telegram', ['tenant' => $owner->tenant_id])
            && ! empty($request['secret_token']));
    }

    public function test_connect_rejects_an_invalid_bot_token(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => false, 'error_code' => 401])]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/settings/telegram/connect', ['bot_token' => 'garbage'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_INVALID_TOKEN');
    }

    public function test_connect_reports_a_clean_error_when_the_webhook_cannot_be_registered(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'my_studio_bot']]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => false, 'description' => 'Bad webhook: no HTTPS']),
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/settings/telegram/connect', ['bot_token' => '123:ABC'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_WEBHOOK_FAILED');
    }

    public function test_a_role_without_tenant_settings_manage_cannot_connect_a_bot(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/settings/telegram/connect', ['bot_token' => '123:ABC'])
            ->assertForbidden();
    }

    public function test_owner_can_disconnect_the_bot(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        Http::fake(['api.telegram.org/*/deleteWebhook' => Http::response(['ok' => true])]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/settings/telegram/disconnect')
            ->assertOk()
            ->assertJsonPath('data.connected', false);

        $this->assertNull($tenant->fresh()->telegram_bot_token);
    }

    public function test_generating_a_customer_link_fails_when_the_tenant_has_no_bot_connected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/link")
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_NOT_CONFIGURED');
    }

    public function test_generating_a_customer_link_returns_a_deep_link_with_a_token(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/link")
            ->assertOk();

        $response->assertJsonPath('data.linked', false);
        $link = $response->json('data.link');
        $this->assertStringStartsWith('https://t.me/my_studio_bot?start=', $link);

        $customer->refresh();
        $this->assertNotNull($customer->telegram_link_token);
        $this->assertStringContainsString($customer->telegram_link_token, $link);
    }

    public function test_requesting_a_link_twice_reuses_the_same_token(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $first = $this->actingAsUser($owner)->postJson("/api/v1/customers/{$customer->id}/telegram/link")->json('data.link');
        $second = $this->actingAsUser($owner)->postJson("/api/v1/customers/{$customer->id}/telegram/link")->json('data.link');

        $this->assertSame($first, $second);
    }

    public function test_an_already_linked_customer_reports_linked_true_without_a_link(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/link")
            ->assertOk()
            ->assertJsonPath('data.linked', true);
    }

    public function test_owner_can_unlink_a_customer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'telegram_chat_id' => '999',
            'telegram_link_token' => 'sometoken',
            'telegram_linked_at' => now(),
        ]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/unlink")
            ->assertOk();

        $customer->refresh();
        $this->assertNull($customer->telegram_chat_id);
        $this->assertNull($customer->telegram_link_token);
    }

    public function test_sending_files_fails_when_the_tenant_has_no_bot_connected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('photo.jpg')],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_NOT_CONFIGURED');
    }

    public function test_sending_files_fails_when_the_customer_has_not_linked_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('photo.jpg')],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_CUSTOMER_NOT_LINKED');
    }

    public function test_owner_can_send_photos_to_a_linked_customer_via_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $response = $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('photo1.jpg'), UploadedFile::fake()->image('photo2.jpg')],
                'caption' => 'Your final photos!',
            ])
            ->assertOk();

        $response->assertJsonPath('data.sent', 2)->assertJsonPath('data.failed', []);
        Http::assertSentCount(2);
    }

    /**
     * A Photographer has albums.update but deliberately NOT customers.update
     * (see config/permissions.php defaults) — this is the "deliver photos"
     * action, so it's gated on albums.update, not customers.update.
     */
    public function test_a_photographer_can_send_photos_despite_lacking_customers_update(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('photo1.jpg')],
            ])
            ->assertOk();
    }

    /**
     * A Photographer lacks customers.update, so link/unlink (customer
     * contact-channel management) stays out of reach even though sending
     * files (albums.update) is allowed.
     */
    public function test_a_photographer_cannot_generate_a_customer_telegram_link(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/link")
            ->assertForbidden();
    }

    public function test_when_some_files_fail_the_response_reports_a_partial_success(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 1]])
            ->push(['ok' => false, 'description' => 'Too Many Requests']),
        ]);

        $response = $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('photo1.jpg'), UploadedFile::fake()->image('photo2.jpg')],
            ])
            ->assertOk();

        $response->assertJsonPath('data.sent', 1);
        $this->assertCount(1, $response->json('data.failed'));
    }

    public function test_invoice_telegram_send_fails_when_customer_not_linked(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send")
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_CUSTOMER_NOT_LINKED');
    }

    public function test_owner_can_send_an_invoice_pdf_via_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999', 'name' => 'Sokha Chan']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send")
            ->assertOk();

        Http::assertSent(function ($request) use ($tenant, $invoice) {
            if (! str_contains($request->url(), '/sendDocument')) {
                return false;
            }

            $caption = collect($request->data())->firstWhere('name', 'caption')['contents'] ?? null;

            return str_contains($caption, "Invoice #{$invoice->invoice_number}")
                && str_contains($caption, $tenant->name)
                && str_contains($caption, 'Sokha Chan')
                && str_contains($caption, 'Please scan the QR code')
                && str_contains($caption, 'Thank you for your business!');
        });
    }

    public function test_owner_can_send_an_invoice_image_via_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send", ['format' => 'image'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendPhoto'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
    }

    /**
     * The text-only mode sends no attachment, so the message must drop the
     * "scan the QR in the attached image" instruction — nothing is attached.
     */
    public function test_owner_can_send_an_invoice_as_text_via_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send", ['format' => 'text'])
            ->assertOk();

        Http::assertSent(function ($request) use ($invoice) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            return str_contains($request['text'], "Invoice #{$invoice->invoice_number}")
                && ! str_contains($request['text'], 'Please scan the QR code');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument') || str_contains($request->url(), '/sendPhoto'));
    }

    public function test_an_unrecognized_invoice_send_format_falls_back_to_pdf(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send", ['format' => 'not-a-real-format'])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
    }

    public function test_webhook_links_the_customer_on_start_and_replies_a_confirmation(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update([
            'telegram_bot_token' => '123:ABC',
            'telegram_bot_username' => 'my_studio_bot',
            'telegram_webhook_secret' => 'shhh-secret',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_link_token' => 'abc123']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true])]);

        $this->postJson("/api/v1/webhooks/telegram/{$tenant->id}", [
            'message' => ['text' => '/start abc123', 'chat' => ['id' => 555]],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'shhh-secret'])
            ->assertOk();

        $customer->refresh();
        $this->assertSame('555', $customer->telegram_chat_id);
        $this->assertNotNull($customer->telegram_linked_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage'));
    }

    public function test_webhook_ignores_a_request_with_the_wrong_secret(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_webhook_secret' => 'shhh-secret']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_link_token' => 'abc123']);

        $this->postJson("/api/v1/webhooks/telegram/{$tenant->id}", [
            'message' => ['text' => '/start abc123', 'chat' => ['id' => 555]],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])
            ->assertOk();

        $this->assertNull($customer->fresh()->telegram_chat_id);
    }

    public function test_webhook_ignores_an_unknown_link_token(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_webhook_secret' => 'shhh-secret']);

        $this->postJson("/api/v1/webhooks/telegram/{$tenant->id}", [
            'message' => ['text' => '/start does-not-exist', 'chat' => ['id' => 555]],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'shhh-secret'])
            ->assertOk();
    }

    public function test_the_album_customer_resource_exposes_telegram_connected_state(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $album = Album::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/albums')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $album->id);
        $this->assertTrue($row['customer']['telegram_connected']);
    }
}

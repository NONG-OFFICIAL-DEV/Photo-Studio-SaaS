<?php

namespace Tests\Feature\Telegram;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class TelegramActivityLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function connectedTenant(): array
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);

        return [$tenant, $owner];
    }

    public function test_a_successful_invoice_send_is_logged(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999', 'name' => 'Sokha Chan']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send")
            ->assertOk();

        $response = $this->actingAsUser($owner)->getJson('/api/v1/telegram/activity')->assertOk();

        $response->assertJsonPath('data.0.customer_name', 'Sokha Chan')
            ->assertJsonPath('data.0.type', 'invoice')
            ->assertJsonPath('data.0.subject_label', $invoice->invoice_number)
            ->assertJsonPath('data.0.format', 'pdf')
            ->assertJsonPath('data.0.status', 'sent')
            ->assertJsonPath('data.0.error_message', null)
            ->assertJsonPath('data.0.sent_by_name', $owner->name);
    }

    public function test_a_failed_invoice_send_is_logged_with_the_telegram_error(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/invoices/{$invoice->id}/telegram/send")
            ->assertStatus(502);

        $response = $this->actingAsUser($owner)->getJson('/api/v1/telegram/activity')->assertOk();

        $response->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.error_message', 'Bad Request: chat not found');
    }

    public function test_a_package_send_is_logged(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Wedding Package']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertOk();

        $response = $this->actingAsUser($owner)->getJson('/api/v1/telegram/activity?type=package')->assertOk();

        $response->assertJsonPath('data.0.type', 'package')
            ->assertJsonPath('data.0.subject_label', 'Wedding Package')
            ->assertJsonPath('data.0.status', 'sent');
    }

    public function test_a_photo_batch_send_is_logged_as_one_entry(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ])
            ->assertOk();

        $response = $this->actingAsUser($owner)->getJson('/api/v1/telegram/activity?type=album')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_label', '2 file(s)')
            ->assertJsonPath('data.0.status', 'sent');
    }

    public function test_a_partial_photo_batch_failure_is_logged_with_a_note_but_still_marked_sent(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 1]])
            ->push(['ok' => false, 'description' => 'Too Many Requests']),
        ]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/telegram/send", [
                'files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ])
            ->assertOk();

        $response = $this->actingAsUser($owner)->getJson('/api/v1/telegram/activity')->assertOk();

        $response->assertJsonPath('data.0.status', 'sent');
        $this->assertStringContainsString('Sent 1 of 2', $response->json('data.0.error_message'));
    }

    public function test_the_customer_scoped_activity_endpoint_only_returns_that_customers_logs(): void
    {
        [$tenant, $owner] = $this->connectedTenant();
        $customerA = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '111']);
        $customerB = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '222']);
        $invoiceA = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerA->id]);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customerB->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)->postJson("/api/v1/invoices/{$invoiceA->id}/telegram/send")->assertOk();
        $this->actingAsUser($owner)->postJson("/api/v1/invoices/{$invoiceB->id}/telegram/send")->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/customers/{$customerA->id}/telegram/activity")
            ->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_label', $invoiceA->invoice_number);
    }

    public function test_a_tenant_cannot_see_another_tenants_telegram_activity(): void
    {
        [$tenantA, $ownerA] = $this->connectedTenant();
        [$tenantB, $ownerB] = $this->connectedTenant();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id, 'telegram_chat_id' => '999']);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id, 'customer_id' => $customerB->id]);
        Http::fake(['api.telegram.org/*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($ownerB)->postJson("/api/v1/invoices/{$invoiceB->id}/telegram/send")->assertOk();

        $response = $this->actingAsUser($ownerA)->getJson('/api/v1/telegram/activity')->assertOk();

        $response->assertJsonCount(0, 'data');
    }

    public function test_a_role_without_customers_view_cannot_see_the_activity_log(): void
    {
        [$tenant] = $this->connectedTenant();
        $viewer = $this->addUserToTenant($tenant, TenantRole::Viewer);

        // Every baseline role has customers.view by default, so temporarily
        // strip it from this tenant's Viewer role to prove the gate
        // actually checks the permission, not just that the user exists.
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        \Spatie\Permission\Models\Role::where(['name' => 'viewer', 'tenant_id' => $tenant->id])
            ->first()
            ->revokePermissionTo('customers.view');

        $this->actingAsUser($viewer)
            ->getJson('/api/v1/telegram/activity')
            ->assertForbidden();
    }
}

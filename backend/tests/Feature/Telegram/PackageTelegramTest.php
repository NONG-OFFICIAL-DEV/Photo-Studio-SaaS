<?php

namespace Tests\Feature\Telegram;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageComponent;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PackageTelegramTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_send_fails_when_the_tenant_has_no_bot_connected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_NOT_CONFIGURED');
    }

    public function test_send_fails_when_the_customer_has_not_linked_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'TELEGRAM_CUSTOMER_NOT_LINKED');
    }

    public function test_owner_can_send_a_package_quote_via_telegram(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Package',
            'description' => 'A full day of coverage.',
            'override_price' => 500,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '999'
            && str_contains($request['text'], 'Wedding Package')
            && str_contains($request['text'], $tenant->name)
            && str_contains($request['text'], 'A full day of coverage.')
            && str_contains($request['text'], 'Price: $500.00')
            && str_contains($request['text'], 'Interested? Reply here or contact us to book!'));
    }

    public function test_the_message_lists_package_components_with_quantity_and_optional_flag(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'override_price' => 300]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Photo Session']);
        PackageComponent::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'service_id' => $service->id,
            'quantity' => 2,
            'is_optional' => false,
        ]);
        $addon = \App\Models\ServiceAddOn::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Drone Shots']);
        PackageComponent::create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'addon_id' => $addon->id,
            'quantity' => 1,
            'is_optional' => true,
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['text'], '• Photo Session x2')
            && str_contains($request['text'], '• Drone Shots x1 (optional)'));
    }

    public function test_a_role_without_packages_send_cannot_send_a_package_quote(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertForbidden();
    }

    public function test_a_cashier_can_send_a_package_quote(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);
        $tenant->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'telegram_chat_id' => '999']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/packages/{$package->id}/telegram/send", ['customer_id' => $customer->id])
            ->assertOk();
    }

    public function test_a_tenant_cannot_send_another_tenants_package(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantB->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($ownerA)
            ->postJson("/api/v1/packages/{$packageB->id}/telegram/send", ['customer_id' => $customerB->id])
            ->assertNotFound();
    }

    /**
     * A customer_id from a DIFFERENT tenant than the package's own must
     * 404 too — Customer::findOrFail() is scoped by the same
     * BelongsToTenant global scope as every other tenant-owned model, so
     * this is enforced by the query itself, not extra controller logic.
     */
    public function test_a_customer_id_from_another_tenant_is_rejected(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $tenantA->update(['telegram_bot_token' => '123:ABC', 'telegram_bot_username' => 'my_studio_bot']);
        $packageA = Package::factory()->create(['tenant_id' => $tenantA->id]);

        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id, 'telegram_chat_id' => '999']);

        $this->actingAsUser($ownerA)
            ->postJson("/api/v1/packages/{$packageA->id}/telegram/send", ['customer_id' => $customerB->id])
            ->assertNotFound();
    }
}

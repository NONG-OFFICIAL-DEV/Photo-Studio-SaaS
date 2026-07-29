<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceAddOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrderCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_create_an_order_with_catalog_and_custom_items(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Wedding Package', 'price' => 500]);
        $addon = ServiceAddOn::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Extra Hour', 'price' => 50]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'discount_amount' => 20,
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
                ['addon_id' => $addon->id, 'quantity' => 2],
                ['name' => 'Custom Retouching', 'unit_price' => 30, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Wedding Package')
            ->assertJsonPath('data.items.0.unit_price', 500)
            ->assertJsonPath('data.items.1.line_total', 100)
            ->assertJsonPath('data.subtotal', 630)
            ->assertJsonPath('data.discount_amount', 20)
            ->assertJsonPath('data.total', 610)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'tenant_id' => $tenant->id, 'total' => 610]);
    }

    public function test_discount_larger_than_subtotal_floors_total_at_zero(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'discount_amount' => 1000,
            'items' => [['name' => 'Small Print', 'unit_price' => 10, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', 0);
    }

    public function test_creating_an_order_requires_customer_and_at_least_one_item(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/orders', [])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.customer_id.0', 'The customer id field is required.')
            ->assertJsonPath('meta.errors.items.0', 'The items field is required.');
    }

    public function test_an_item_without_a_catalog_reference_or_custom_name_and_price_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['quantity' => 1]],
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('items.0', $response->json('meta.errors'));
    }

    public function test_owner_can_view_update_and_delete_an_order(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/orders/{$order->id}", ['notes' => 'Handle with care'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Handle with care');

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertOk();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }
}

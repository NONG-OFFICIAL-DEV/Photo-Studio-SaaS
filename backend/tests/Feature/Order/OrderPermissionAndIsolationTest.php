<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrderPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_viewer_cannot_create_an_order(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/orders', ['items' => [['name' => 'X', 'unit_price' => 1, 'quantity' => 1]]])
            ->assertForbidden();
    }

    public function test_cashier_can_create_but_not_delete_an_order(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $order = $this->actingAsUser($cashier)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customer->id,
                'items' => [['name' => 'Print', 'unit_price' => 20, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsUser($cashier)
            ->deleteJson("/api/v1/orders/{$order['id']}")
            ->assertForbidden();
    }

    public function test_editor_cannot_create_or_delete_orders(): void
    {
        [$tenant, $editor] = $this->createTenantWithUser(TenantRole::Editor);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($editor)
            ->postJson('/api/v1/orders', ['items' => []])
            ->assertForbidden();

        $this->actingAsUser($editor)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_order(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $orderB = Order::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/orders/{$orderB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_order_list_never_includes_another_tenants_orders(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Order::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Order::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_customer_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customerB->id,
                'items' => [['name' => 'X', 'unit_price' => 1, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_service_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerA = Customer::factory()->create(['tenant_id' => $ownerA->tenant_id]);
        $serviceB = \App\Models\Service::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customerA->id,
                'items' => [['service_id' => $serviceB->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_moves_through_the_full_lifecycle(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $editor = $this->addUserToTenant($tenant, TenantRole::Editor);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $response = $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/start-production", ['assigned_user_id' => $editor->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_production')
            ->assertJsonPath('data.editing_task.status', 'pending')
            ->assertJsonPath('data.editing_task.assigned_user.id', $editor->id);

        $taskId = $response->json('data.editing_task.id');
        $this->assertDatabaseHas('editing_tasks', ['id' => $taskId, 'order_id' => $order->id, 'assigned_user_id' => $editor->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/ready-for-delivery")
            ->assertStatus(422);

        $this->actingAsUser($editor)->postJson("/api/v1/editing-tasks/{$taskId}/start")->assertOk();
        $this->actingAsUser($editor)->postJson("/api/v1/editing-tasks/{$taskId}/complete")->assertOk();

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/ready-for-delivery")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_delivery');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_cannot_start_production_before_confirming(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/start-production", [])
            ->assertStatus(422);
    }

    public function test_delivered_orders_cannot_be_cancelled(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $order->update(['status' => 'delivered']);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'too late'])
            ->assertStatus(422);
    }

    public function test_cancelling_requires_a_reason(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [])
            ->assertStatus(422);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Customer changed mind'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Customer changed mind');
    }

    public function test_line_items_cannot_be_changed_once_in_production(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        $order->update(['status' => 'confirmed']);
        $this->actingAsUser($owner)->postJson("/api/v1/orders/{$order->id}/start-production", []);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/orders/{$order->id}", [
                'items' => [['name' => 'New Item', 'unit_price' => 10, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_order_status_changes_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)->postJson("/api/v1/orders/{$order->id}/confirm");

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $order->id,
            'log_name' => 'order',
            'tenant_id' => $tenant->id,
        ]);
    }
}

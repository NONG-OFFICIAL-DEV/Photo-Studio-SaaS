<?php

namespace Tests\Feature\Inventory;

use App\Enums\TenantRole;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_stock_in_increases_quantity_on_hand(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 0]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", [
                'type' => 'stock_in',
                'quantity' => 100,
                'reason' => 'Initial stock',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_on_hand', 100)
            ->assertJsonPath('data.is_low_stock', false);
    }

    public function test_stock_out_decreases_quantity_on_hand(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 100]);
        $item->movements()->create(['tenant_id' => $tenant->id, 'type' => 'stock_in', 'quantity' => 100, 'moved_at' => now()->toDateString()]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", [
                'type' => 'stock_out',
                'quantity' => 30,
                'reason' => 'Used in Order #123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_on_hand', 70);
    }

    public function test_stock_out_cannot_exceed_quantity_on_hand(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 0]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_out', 'quantity' => 10])
            ->assertStatus(422);
    }

    public function test_deleting_a_movement_recomputes_quantity_on_hand(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 0]);

        $movementId = $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_in', 'quantity' => 50])
            ->assertJsonPath('data.quantity_on_hand', 50)
            ->json('data.movements.0.id');

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/inventory-items/{$item->id}/movements/{$movementId}")
            ->assertOk()
            ->assertJsonPath('data.quantity_on_hand', 0);
    }

    public function test_low_stock_flag_reflects_the_reorder_threshold(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 0, 'reorder_threshold' => 20]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_in', 'quantity' => 15])
            ->assertJsonPath('data.is_low_stock', true);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_in', 'quantity' => 10])
            ->assertJsonPath('data.is_low_stock', false);
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Enums\TenantRole;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InventoryItemCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_an_inventory_item(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/inventory-items', [
                'name' => 'Photo Paper (A4)',
                'sku' => 'PAPER-A4',
                'unit' => 'sheet',
                'category' => 'Printing',
                'reorder_threshold' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Photo Paper (A4)')
            ->assertJsonPath('data.quantity_on_hand', 0)
            ->assertJsonPath('data.is_low_stock', true);

        $this->assertDatabaseHas('inventory_items', ['id' => $response->json('data.id'), 'tenant_id' => $tenant->id]);
    }

    public function test_it_creates_an_inventory_item_with_an_initial_quantity(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/inventory-items', [
                'name' => 'Photo Paper (A4)',
                'unit' => 'sheet',
                'initial_quantity' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_on_hand', 100);

        $item = \App\Models\InventoryItem::find($response->json('data.id'));
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame('Initial stock', $item->movements()->first()->reason);
    }

    public function test_it_creates_an_inventory_item_without_an_initial_quantity(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/inventory-items', ['name' => 'Photo Paper (A4)', 'unit' => 'sheet'])
            ->assertCreated()
            ->assertJsonPath('data.quantity_on_hand', 0);

        $item = \App\Models\InventoryItem::find($response->json('data.id'));
        $this->assertSame(0, $item->movements()->count());
    }

    public function test_sku_is_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner)->postJson('/api/v1/inventory-items', ['name' => 'A', 'sku' => 'SKU-1', 'unit' => 'unit'])->assertCreated();

        $this->actingAsUser($owner)
            ->postJson('/api/v1/inventory-items', ['name' => 'B', 'sku' => 'SKU-1', 'unit' => 'unit'])
            ->assertStatus(422);
    }

    public function test_two_tenants_can_each_use_the_same_sku(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->postJson('/api/v1/inventory-items', ['name' => 'A', 'sku' => 'SKU-1', 'unit' => 'unit'])->assertCreated();
        $this->actingAsUser($ownerB)->postJson('/api/v1/inventory-items', ['name' => 'B', 'sku' => 'SKU-1', 'unit' => 'unit'])->assertCreated();
    }

    public function test_it_lists_items_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        InventoryItem::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/inventory-items')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_filters_low_stock_items(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 5, 'reorder_threshold' => 10]);
        InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'quantity_on_hand' => 50, 'reorder_threshold' => 10]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/inventory-items?low_stock=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_updates_an_item(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/inventory-items/{$item->id}", ['name' => 'Renamed Item'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Item');
    }

    public function test_it_deletes_an_item(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/inventory-items/{$item->id}")
            ->assertOk();

        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
    }
}

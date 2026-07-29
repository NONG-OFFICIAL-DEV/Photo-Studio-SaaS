<?php

namespace Tests\Feature\Inventory;

use App\Enums\TenantRole;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InventoryPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_photographer_can_view_and_adjust_stock_but_not_create_or_delete_items(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)->getJson('/api/v1/inventory-items')->assertOk();

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_in', 'quantity' => 5])
            ->assertCreated();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/inventory-items', ['name' => 'X', 'unit' => 'unit'])
            ->assertForbidden();

        $this->actingAsUser($photographer)
            ->deleteJson("/api/v1/inventory-items/{$item->id}")
            ->assertForbidden();
    }

    public function test_cashier_can_view_but_not_adjust_stock(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);
        $item = InventoryItem::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($cashier)->getJson('/api/v1/inventory-items')->assertOk();

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/inventory-items/{$item->id}/movements", ['type' => 'stock_in', 'quantity' => 5])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_inventory_item(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $itemB = InventoryItem::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/inventory-items/{$itemB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_item_list_never_includes_another_tenants_items(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        InventoryItem::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        InventoryItem::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/inventory-items')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}

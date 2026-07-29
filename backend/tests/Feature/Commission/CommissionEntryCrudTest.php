<?php

namespace Tests\Feature\Commission;

use App\Enums\TenantRole;
use App\Models\CommissionEntry;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CommissionEntryCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_manager_can_record_a_commission_entry_linked_to_an_order(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/commission-entries', [
                'user_id' => $photographer->id,
                'order_id' => $order->id,
                'amount' => 80,
                'earned_date' => '2026-07-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 80)
            ->assertJsonPath('data.user.id', $photographer->id);

        $this->assertDatabaseHas('commission_entries', ['id' => $response->json('data.id'), 'tenant_id' => $tenant->id]);
    }

    public function test_it_lists_entries_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        CommissionEntry::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/commission-entries')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_updates_an_entry(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $entry = CommissionEntry::factory()->create(['tenant_id' => $tenant->id, 'amount' => 50]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/commission-entries/{$entry->id}", ['amount' => 65])
            ->assertOk()
            ->assertJsonPath('data.amount', 65);
    }

    public function test_it_deletes_an_entry(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $entry = CommissionEntry::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/commission-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('commission_entries', ['id' => $entry->id]);
    }
}

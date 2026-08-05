<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrdersReportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_breaks_down_orders_by_status_within_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'delivered', 'total' => 500, 'created_at' => '2026-07-05 10:00:00']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'delivered', 'total' => 300, 'created_at' => '2026-07-10 10:00:00']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'pending', 'total' => 100, 'created_at' => '2026-07-15 10:00:00']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'delivered', 'total' => 999, 'created_at' => '2026-08-01 10:00:00']);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/orders?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total_count', 3)
            ->assertJsonPath('data.total_value', 900);

        $deliveredRow = collect($response->json('data.by_status'))->firstWhere('status', 'delivered');
        $this->assertSame(2, $deliveredRow['count']);
        $this->assertEquals(800, $deliveredRow['value']);

        $breakdown = collect($response->json('data.breakdown'))->keyBy('period');
        $this->assertSame(1, $breakdown['2026-07-05']['count']);
        $this->assertEquals(500, $breakdown['2026-07-05']['value']);
        $this->assertFalse($breakdown->has('2026-08-01'));
    }
}

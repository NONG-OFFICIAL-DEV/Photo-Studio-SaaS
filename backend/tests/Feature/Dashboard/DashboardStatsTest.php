<?php

namespace Tests\Feature\Dashboard;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\EditingTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_computes_today_and_monthly_revenue(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Payment::factory()->create(['tenant_id' => $tenant->id, 'amount' => 100, 'paid_at' => '2026-07-15']);
        Payment::factory()->create(['tenant_id' => $tenant->id, 'amount' => 50, 'paid_at' => '2026-07-01']);
        Payment::factory()->create(['tenant_id' => $tenant->id, 'amount' => 999, 'paid_at' => '2026-06-30']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.today_revenue', 100)
            ->assertJsonPath('data.monthly_revenue', 150);
    }

    public function test_it_counts_new_customers_this_month(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Customer::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-07-10 00:00:00']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-06-30 00:00:00']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.new_customers', 1);
    }

    public function test_it_counts_bookings_this_month(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Booking::factory()->create(['tenant_id' => $tenant->id, 'starts_at' => '2026-07-20 10:00:00', 'ends_at' => '2026-07-20 11:00:00']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'starts_at' => '2026-08-01 10:00:00', 'ends_at' => '2026-08-01 11:00:00']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.bookings', 1);
    }

    public function test_it_counts_pending_editing_and_ready_for_delivery(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $orderInProduction = Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'in_production']);
        EditingTask::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $orderInProduction->id, 'status' => 'in_progress']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'ready_for_delivery']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'status' => 'ready_for_delivery']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_editing', 1)
            ->assertJsonPath('data.ready_for_delivery', 2);
    }

    public function test_it_ranks_top_services_by_revenue_this_month(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Wedding Package', 'price' => 500]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id]);
        OrderItem::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'service_id' => $service->id,
            'name' => 'Wedding Package',
            'unit_price' => 500,
            'quantity' => 2,
            'line_total' => 1000,
        ]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.top_services.0.name', 'Wedding Package')
            ->assertJsonPath('data.top_services.0.revenue', 1000);
    }

    public function test_a_tenants_stats_never_include_another_tenants_data(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Payment::factory()->create(['tenant_id' => $tenantB->id, 'amount' => 5000, 'paid_at' => '2026-07-15']);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.monthly_revenue', 0);
    }
}

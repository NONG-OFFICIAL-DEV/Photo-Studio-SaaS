<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingsReportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_breaks_down_bookings_by_type_and_status_within_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Booking::factory()->create(['tenant_id' => $tenant->id, 'type' => 'wedding', 'status' => 'completed', 'starts_at' => '2026-07-05 10:00:00', 'ends_at' => '2026-07-05 12:00:00']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'type' => 'wedding', 'status' => 'confirmed', 'starts_at' => '2026-07-10 10:00:00', 'ends_at' => '2026-07-10 12:00:00']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'type' => 'portrait', 'status' => 'completed', 'starts_at' => '2026-07-15 10:00:00', 'ends_at' => '2026-07-15 12:00:00']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'type' => 'portrait', 'status' => 'completed', 'starts_at' => '2026-08-01 10:00:00', 'ends_at' => '2026-08-01 12:00:00']);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/bookings?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total', 3);

        $weddingRow = collect($response->json('data.by_type'))->firstWhere('type', 'wedding');
        $this->assertSame(2, $weddingRow['count']);

        $completedRow = collect($response->json('data.by_status'))->firstWhere('status', 'completed');
        $this->assertSame(2, $completedRow['count']);
    }
}

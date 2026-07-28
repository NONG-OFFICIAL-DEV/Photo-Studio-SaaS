<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_returns_only_bookings_overlapping_the_requested_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $inRange = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'In Range',
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'ends_at' => now()->addDays(5)->setTime(12, 0),
        ]);

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Out Of Range',
            'starts_at' => now()->addDays(40)->setTime(10, 0),
            'ends_at' => now()->addDays(40)->setTime(12, 0),
        ]);

        $response = $this->actingAsUser($owner)->getJson(
            '/api/v1/bookings/calendar?start='.now()->startOfMonth()->toDateString()
                .'&end='.now()->addMonth()->endOfMonth()->toDateString()
        );

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('In Range', $titles);
        $this->assertNotContains('Out Of Range', $titles);
        $this->assertTrue($inRange->exists);
    }

    public function test_calendar_requires_start_and_end(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/bookings/calendar')
            ->assertStatus(422);
    }

    public function test_calendar_never_includes_another_tenants_bookings(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Booking::factory()->create([
            'tenant_id' => $tenantA->id,
            'title' => 'Tenant A Booking',
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'ends_at' => now()->addDays(5)->setTime(12, 0),
        ]);
        Booking::factory()->create([
            'tenant_id' => $tenantB->id,
            'title' => 'Tenant B Booking',
            'starts_at' => now()->addDays(5)->setTime(10, 0),
            'ends_at' => now()->addDays(5)->setTime(12, 0),
        ]);

        $response = $this->actingAsUser($ownerA)->getJson(
            '/api/v1/bookings/calendar?start='.now()->startOfMonth()->toDateString()
                .'&end='.now()->addMonth()->endOfMonth()->toDateString()
        );

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Tenant A Booking', $titles);
        $this->assertNotContains('Tenant B Booking', $titles);
    }
}

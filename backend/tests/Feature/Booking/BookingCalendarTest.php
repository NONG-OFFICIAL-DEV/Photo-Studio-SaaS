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

        // The queried range's end (endOfMonth of next month) varies with
        // today's date — anywhere from ~28 to ~62 days out depending on
        // where "now" falls in the current month and how long the current/
        // next months are. A fixed +40 days was sometimes still INSIDE that
        // range (e.g. early in a 31-day month followed by another long
        // month), making this assertion fail depending on the day it ran.
        // +3 months is comfortably beyond the range's upper bound regardless
        // of the date.
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Out Of Range',
            'starts_at' => now()->addMonths(3)->setTime(10, 0),
            'ends_at' => now()->addMonths(3)->setTime(12, 0),
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

    /**
     * Regression test: a booking that starts partway through the requested
     * end date used to be silently dropped — inRange() compared starts_at
     * against midnight of $end, excluding anything on $end's own date. This
     * also blocked single-day ranges outright (start === end), needed for
     * the calendar's day/timeline view.
     */
    public function test_a_booking_on_the_requested_end_date_is_included(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $target = now()->addDays(5)->startOfDay();

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'On End Date',
            'starts_at' => $target->copy()->setTime(14, 0),
            'ends_at' => $target->copy()->setTime(15, 0),
        ]);

        $response = $this->actingAsUser($owner)->getJson(
            '/api/v1/bookings/calendar?start='.$target->toDateString().'&end='.$target->toDateString()
        );

        $response->assertOk();
        $this->assertContains('On End Date', collect($response->json('data'))->pluck('title'));
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

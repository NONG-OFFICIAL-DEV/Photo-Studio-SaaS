<?php

namespace Tests\Feature\Attendance;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AttendanceClockTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_clocking_in_before_the_expected_start_time_is_present(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->travelTo(Carbon::parse('2026-08-03 08:45:00'));

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-in')
            ->assertOk()
            ->assertJsonPath('data.status', 'present');
    }

    public function test_clocking_in_after_the_expected_start_time_is_late(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->travelTo(Carbon::parse('2026-08-03 09:30:00'));

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-in')
            ->assertOk()
            ->assertJsonPath('data.status', 'late');
    }

    public function test_cannot_clock_in_twice_the_same_day(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)->postJson('/api/v1/attendance/clock-in')->assertOk();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_cannot_clock_out_before_clocking_in(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-out')
            ->assertStatus(422);
    }

    public function test_clock_out_computes_hours_worked(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->travelTo(Carbon::parse('2026-08-03 09:00:00'));
        $this->actingAsUser($photographer)->postJson('/api/v1/attendance/clock-in')->assertOk();

        $this->travelTo(Carbon::parse('2026-08-03 17:00:00'));
        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('data.hours_worked', 8);
    }

    public function test_cannot_clock_out_twice(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)->postJson('/api/v1/attendance/clock-in')->assertOk();
        $this->actingAsUser($photographer)->postJson('/api/v1/attendance/clock-out')->assertOk();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance/clock-out')
            ->assertStatus(422);
    }

    public function test_today_endpoint_reflects_current_state(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->travelTo(Carbon::parse('2026-08-03 08:45:00'));

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAsUser($photographer)->postJson('/api/v1/attendance/clock-in')->assertOk();

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.status', 'present');
    }

    public function test_viewer_cannot_clock_in(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/attendance/clock-in')
            ->assertForbidden();
    }
}

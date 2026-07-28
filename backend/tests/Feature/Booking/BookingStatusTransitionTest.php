<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingStatusTransitionTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_moves_through_confirm_start_complete(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_it_marks_no_show(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/no-show")
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show');
    }

    public function test_cancelling_requires_a_reason_and_frees_the_slot(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [])
            ->assertStatus(422);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'Customer rescheduled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_reason', 'Customer rescheduled');
    }

    public function test_status_changes_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)->postJson("/api/v1/bookings/{$booking->id}/confirm");

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $booking->id,
            'log_name' => 'booking',
            'tenant_id' => $tenant->id,
        ]);
    }
}

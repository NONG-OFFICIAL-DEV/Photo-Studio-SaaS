<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_create_a_booking(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', [
            'customer_id' => $customer->id,
            'type' => 'wedding',
            'title' => 'Sok & Dara Wedding',
            'location_type' => 'studio',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHours(2)->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Sok & Dara Wedding')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->assertDatabaseHas('bookings', ['title' => 'Sok & Dara Wedding', 'tenant_id' => $tenant->id]);
    }

    public function test_creating_a_booking_requires_a_customer_and_valid_times(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'type' => 'wedding',
                'location_type' => 'studio',
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->addHours(1)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.customer_id.0', 'The customer id field is required.')
            ->assertJsonPath('meta.errors.ends_at.0', 'The ends at field must be a date after starts at.');
    }

    public function test_creating_a_booking_with_a_past_start_date_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customer->id,
                'type' => 'wedding',
                'location_type' => 'studio',
                'starts_at' => now()->subDay()->toIso8601String(),
                'ends_at' => now()->subDay()->addHours(2)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.starts_at.0', 'The starts at field must be a date after or equal to today.');
    }

    public function test_updating_a_booking_to_a_past_start_date_is_still_allowed(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/bookings/{$booking->id}", [
                'starts_at' => now()->subWeek()->toIso8601String(),
                'ends_at' => now()->subWeek()->addHours(2)->toIso8601String(),
            ])
            ->assertOk();
    }

    public function test_on_location_bookings_require_an_address(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customer->id,
                'type' => 'event',
                'location_type' => 'on_location',
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->addDay()->addHours(2)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.location_address.0', 'The location address field is required when location type is on_location.');
    }

    public function test_owner_can_view_update_and_delete_a_booking(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Original Shoot']);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Original Shoot');

        $this->actingAsUser($owner)
            ->putJson("/api/v1/bookings/{$booking->id}", ['title' => 'Rescheduled Shoot'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Rescheduled Shoot');

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertOk();

        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }
}

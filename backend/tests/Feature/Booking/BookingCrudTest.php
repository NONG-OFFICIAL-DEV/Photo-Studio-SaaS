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

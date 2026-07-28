<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_rejects_a_second_booking_that_overlaps_the_same_photographer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customer->id,
                'assigned_user_id' => $photographer->id,
                'type' => 'portrait',
                'location_type' => 'studio',
                'starts_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(13, 0)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.assigned_user_id.0', 'This staff member already has a booking during that time.');
    }

    public function test_it_allows_a_non_overlapping_booking_for_the_same_photographer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customer->id,
                'assigned_user_id' => $photographer->id,
                'type' => 'portrait',
                'location_type' => 'studio',
                'starts_at' => now()->addDay()->setTime(13, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(14, 0)->toIso8601String(),
            ])
            ->assertCreated();
    }

    public function test_a_cancelled_booking_does_not_block_the_slot(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        Booking::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customer->id,
                'assigned_user_id' => $photographer->id,
                'type' => 'portrait',
                'location_type' => 'studio',
                'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(12, 0)->toIso8601String(),
            ])
            ->assertCreated();
    }

    public function test_updating_a_booking_into_conflict_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $movable = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(15, 0),
        ]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/bookings/{$movable->id}", [
                'starts_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(13, 0)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.assigned_user_id.0', 'This staff member already has a booking during that time.');
    }

    public function test_updating_a_booking_without_changing_its_own_time_is_not_a_false_conflict(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_user_id' => $photographer->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/bookings/{$booking->id}", ['title' => 'Updated title only'])
            ->assertOk();
    }
}

<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_viewer_cannot_create_a_booking(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/bookings', ['type' => 'wedding'])
            ->assertForbidden();
    }

    public function test_viewer_can_list_bookings(): void
    {
        [$tenant, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);
        Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($viewer)
            ->getJson('/api/v1/bookings')
            ->assertOk();
    }

    public function test_photographer_cannot_update_a_booking_not_assigned_to_them(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->putJson("/api/v1/bookings/{$booking->id}", ['title' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_photographer_can_update_a_booking_assigned_to_them(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id, 'assigned_user_id' => $photographer->id]);

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/bookings/{$booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_photographer_cannot_delete_a_booking(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id, 'assigned_user_id' => $photographer->id]);

        $this->actingAsUser($photographer)
            ->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden();
    }

    public function test_editor_cannot_cancel_a_booking(): void
    {
        [$tenant, $editor] = $this->createTenantWithUser(TenantRole::Editor);
        $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($editor)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'test'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_booking(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $bookingB = Booking::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/bookings/{$bookingB->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_update_another_tenants_booking(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $bookingB = Booking::factory()->create(['tenant_id' => $tenantB->id, 'title' => 'Original']);

        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/bookings/{$bookingB->id}", ['title' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('bookings', ['id' => $bookingB->id, 'title' => 'Original']);
    }

    public function test_a_tenants_booking_list_never_includes_another_tenants_bookings(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Booking::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Booking::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_customer_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerB = \App\Models\Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/bookings', [
                'customer_id' => $customerB->id,
                'type' => 'wedding',
                'location_type' => 'studio',
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->addDay()->addHours(2)->toIso8601String(),
            ])
            ->assertStatus(422);
    }
}

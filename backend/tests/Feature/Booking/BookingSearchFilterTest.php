<?php

namespace Tests\Feature\Booking;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BookingSearchFilterTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_searches_by_title_or_customer_name(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $alice = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Wonderland']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $alice->id, 'title' => 'Studio Shoot']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Unrelated Booking']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/bookings?search=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Studio Shoot');
    }

    public function test_it_filters_by_status_and_type(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Booking::factory()->confirmed()->create(['tenant_id' => $tenant->id, 'type' => 'wedding', 'title' => 'Confirmed Wedding']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'type' => 'portrait', 'title' => 'Pending Portrait']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/bookings?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Confirmed Wedding');

        $this->actingAsUser($owner)
            ->getJson('/api/v1/bookings?type=portrait')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pending Portrait');
    }

    public function test_it_filters_by_assigned_user(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        Booking::factory()->create(['tenant_id' => $tenant->id, 'assigned_user_id' => $photographer->id, 'title' => 'Assigned']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Unassigned']);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/bookings?assigned_user_id={$photographer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Assigned');
    }

    public function test_pagination_and_sorting_match_the_server_table_contract(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Booking A']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Booking B']);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Booking C']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/bookings?perPage=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }
}

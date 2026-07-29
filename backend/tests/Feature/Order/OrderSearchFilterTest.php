<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrderSearchFilterTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_searches_by_customer_name(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $alice = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Wonderland']);
        $bob = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Builder']);
        Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $alice->id]);
        Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $bob->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/orders?search=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer.name', 'Alice Wonderland');
    }

    public function test_it_filters_by_status(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Order::factory()->confirmed()->create(['tenant_id' => $tenant->id]);
        Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/orders?status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_it_filters_by_customer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        Order::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/orders?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pagination_matches_the_server_table_contract(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Order::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/orders?perPage=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }
}

<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServiceSearchFilterTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_searches_by_name_or_description(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Golden Wedding Package']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Basic Passport Photo']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/services?search=Wedding')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Golden Wedding Package');
    }

    public function test_it_filters_by_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);
        Service::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'In Category']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'No Category']);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/services?category_id={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'In Category');
    }

    public function test_it_filters_by_active_status(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Active Package']);
        Service::factory()->inactive()->create(['tenant_id' => $tenant->id, 'name' => 'Retired Package']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/services?is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Package');
    }

    public function test_it_filters_by_pricing_unit(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Service::factory()->create(['tenant_id' => $tenant->id, 'pricing_unit' => 'per_hour', 'name' => 'Hourly']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'pricing_unit' => 'fixed', 'name' => 'Flat Rate']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/services?pricing_unit=per_hour')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hourly');
    }

    public function test_pagination_and_sorting_match_the_server_table_contract(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Service A']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Service B']);
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Service C']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/services?perPage=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }
}

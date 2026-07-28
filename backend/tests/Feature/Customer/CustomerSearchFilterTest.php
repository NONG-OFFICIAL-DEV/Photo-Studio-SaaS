<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerSearchFilterTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_searches_by_name_email_or_phone(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alice Wonderland']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bob Builder']);

        $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/v1/customers?search=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Wonderland');
    }

    public function test_it_filters_by_favorite(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->favorite()->create(['tenant_id' => $tenant->id, 'name' => 'Favorite One']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Regular One']);

        $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/v1/customers?is_favorite=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Favorite One');
    }

    public function test_it_filters_by_blacklisted(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->blacklisted()->create(['tenant_id' => $tenant->id, 'name' => 'Blacklisted One']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Clean One']);

        $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/v1/customers?is_blacklisted=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Blacklisted One');
    }

    public function test_it_filters_by_tag(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $tag = $this->withHeaders($this->authHeader($owner))
            ->postJson('/api/v1/customers/tags', ['name' => 'VIP'])
            ->json('data');

        $tagged = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Tagged']);
        $tagged->tags()->attach($tag['id']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Untagged']);

        $this->withHeaders($this->authHeader($owner))
            ->getJson("/api/v1/customers?tag_id={$tag['id']}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tagged');
    }

    public function test_pagination_and_sorting_match_the_server_table_contract(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Aaron']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Zack']);
        Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Mia']);

        $response = $this->withHeaders($this->authHeader($owner))
            ->getJson('/api/v1/customers?perPage=2&page=1&sortBy=name&sortDesc=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame(['Aaron', 'Mia'], $names->all());
    }
}

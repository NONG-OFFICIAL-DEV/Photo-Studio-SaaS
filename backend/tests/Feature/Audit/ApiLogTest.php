<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ApiLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_a_mutating_request_is_recorded(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/customers', ['name' => 'Api Log Customer'])
            ->assertCreated();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/api-logs')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('path', '/api/v1/customers');
        $this->assertNotNull($entry);
        $this->assertSame('POST', $entry['method']);
        $this->assertSame(201, $entry['status_code']);
        $this->assertSame($owner->id, $entry['user']['id']);
    }

    public function test_a_successful_get_request_is_not_recorded(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/api-logs')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('path', '/api/v1/customers');
        $this->assertNull($entry);
    }

    public function test_a_failed_get_request_is_recorded(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/customers/00000000-0000-0000-0000-000000000000')->assertNotFound();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/api-logs')
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame(404, $entry['status_code']);
    }

    public function test_api_logs_are_isolated_per_tenant(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->postJson('/api/v1/customers', ['name' => 'Tenant A Customer'])->assertCreated();

        $response = $this->actingAsUser($ownerB)
            ->getJson('/api/v1/audit/api-logs')
            ->assertOk();

        $userIds = collect($response->json('data'))->pluck('user.id');
        $this->assertFalse($userIds->contains($ownerA->id));
    }
}

<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_creating_a_customer_is_recorded_in_the_activity_log(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/customers', ['name' => 'Activity Log Customer'])
            ->assertCreated();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/activity')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('log_name', 'customer');
        $this->assertNotNull($entry);
        $this->assertSame('created', $entry['event']);
        $this->assertSame($owner->id, $entry['causer']['id']);
    }

    public function test_the_activity_log_excludes_audit_login_and_security_entries(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->putJson('/api/v1/settings', ['name' => 'Renamed'])->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/activity')
            ->assertOk();

        $logNames = collect($response->json('data'))->pluck('log_name');
        $this->assertFalse($logNames->contains('audit'));
        $this->assertFalse($logNames->contains('login'));
        $this->assertFalse($logNames->contains('security'));
    }

    public function test_activity_log_is_isolated_per_tenant(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->postJson('/api/v1/customers', ['name' => 'Tenant A Customer'])->assertCreated();

        $response = $this->actingAsUser($ownerB)
            ->getJson('/api/v1/audit/activity')
            ->assertOk();

        $causerIds = collect($response->json('data'))->pluck('causer.id');
        $this->assertFalse($causerIds->contains($ownerA->id));
    }

    public function test_a_photographer_cannot_view_the_activity_log(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/audit/activity')
            ->assertForbidden();
    }
}

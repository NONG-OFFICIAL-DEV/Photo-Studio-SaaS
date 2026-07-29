<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_updating_tenant_settings_is_recorded_in_the_audit_log(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/settings', ['name' => 'Renamed Studio'])
            ->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/log')
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame('Tenant settings updated', $entry['description']);
        $this->assertSame($owner->id, $entry['causer']['id']);
    }

    public function test_suspending_a_tenant_is_recorded_in_the_audit_log(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = \App\Models\User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend")
            ->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/log?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('suspended', $entry['description']);
        $this->assertSame($superAdmin->id, $entry['causer']['id']);
    }

    public function test_a_manager_cannot_see_another_tenants_audit_log(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->putJson('/api/v1/settings', ['name' => 'Tenant A Renamed'])->assertOk();

        $response = $this->actingAsUser($ownerB)
            ->getJson('/api/v1/audit/log')
            ->assertOk();

        $this->assertEmpty($response->json('data'));
    }
}

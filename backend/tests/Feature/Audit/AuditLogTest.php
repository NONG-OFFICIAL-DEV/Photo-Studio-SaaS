<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Audit log viewing is platform-admin-only — no tenant role, including
 * Owner, can ever hold 'audit.view'. Recording still happens for every
 * tenant regardless of who can view it.
 */
class AuditLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_no_tenant_role_can_view_the_audit_log(): void
    {
        foreach (TenantRole::cases() as $role) {
            [, $user] = $this->createTenantWithUser($role);
            $this->actingAsUser($user)->getJson('/api/v1/audit/log')->assertForbidden();
        }
    }

    public function test_updating_tenant_settings_is_recorded_in_the_audit_log(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->putJson('/api/v1/settings', ['name' => 'Renamed Studio'])
            ->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/log?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame('Tenant settings updated', $entry['description']);
        $this->assertSame($owner->id, $entry['causer']['id']);
    }

    public function test_suspending_a_tenant_is_recorded_in_the_audit_log(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $superAdmin = $this->superAdmin();

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
}

<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Activity log viewing is platform-admin-only — no tenant role, including
 * Owner, can ever hold 'audit.view'. Recording still happens for every
 * tenant regardless of who can view it.
 */
class ActivityLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_no_tenant_role_can_view_the_activity_log(): void
    {
        foreach (TenantRole::cases() as $role) {
            [, $user] = $this->createTenantWithUser($role);
            $this->actingAsUser($user)->getJson('/api/v1/audit/activity')->assertForbidden();
        }
    }

    public function test_creating_a_customer_is_recorded_in_the_activity_log(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/customers', ['name' => 'Activity Log Customer'])
            ->assertCreated();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/activity?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('log_name', 'customer');
        $this->assertNotNull($entry);
        $this->assertSame('created', $entry['event']);
        $this->assertSame($owner->id, $entry['causer']['id']);
    }

    public function test_the_activity_log_excludes_audit_login_and_security_entries(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->putJson('/api/v1/settings', ['name' => 'Renamed'])->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/activity?tenant_id={$tenant->id}")
            ->assertOk();

        $logNames = collect($response->json('data'))->pluck('log_name');
        $this->assertFalse($logNames->contains('audit'));
        $this->assertFalse($logNames->contains('login'));
        $this->assertFalse($logNames->contains('security'));
    }
}

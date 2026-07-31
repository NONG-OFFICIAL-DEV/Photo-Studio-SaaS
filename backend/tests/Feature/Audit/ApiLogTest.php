<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * API log VIEWING is platform-admin-only (see config/permissions.php —
 * 'audit.view' isn't in the catalog, so no tenant role can ever hold it).
 * Recording still happens for every tenant; these tests verify that via the
 * admin surface (App\Http\Controllers\Api\V1\Admin\AdminAuditController),
 * which is where a super admin investigates it.
 */
class ApiLogTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_no_tenant_role_can_view_api_logs(): void
    {
        foreach (TenantRole::cases() as $role) {
            [, $user] = $this->createTenantWithUser($role);

            $this->actingAsUser($user)
                ->getJson('/api/v1/audit/api-logs')
                ->assertForbidden();
        }
    }

    public function test_a_mutating_request_is_recorded(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/customers', ['name' => 'Api Log Customer'])
            ->assertCreated();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/api-logs?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('path', '/api/v1/customers');
        $this->assertNotNull($entry);
        $this->assertSame('POST', $entry['method']);
        $this->assertSame(201, $entry['status_code']);
        $this->assertSame($owner->id, $entry['user']['id']);
    }

    public function test_a_successful_get_request_is_not_recorded(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/customers')->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/api-logs?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('path', '/api/v1/customers');
        $this->assertNull($entry);
    }

    public function test_a_failed_get_request_is_recorded(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)->getJson('/api/v1/customers/00000000-0000-0000-0000-000000000000')->assertNotFound();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/api-logs?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame(404, $entry['status_code']);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class EnsureSuperAdminTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/analytics')->assertUnauthorized();
    }

    public function test_a_regular_tenant_owner_is_forbidden(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/admin/analytics')
            ->assertForbidden();
    }

    public function test_a_super_admin_is_granted_access(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/analytics')
            ->assertOk();
    }

    /**
     * Confirms the admin group is registered OUTSIDE the tenant/
     * subscription.active chain — a super admin has no tenant_id and no
     * subscription, so if this group accidentally inherited that
     * middleware, every admin request would 403.
     */
    public function test_super_admin_has_no_tenant_and_still_succeeds(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);

        $this->assertNull($superAdmin->tenant_id);

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/tenants')
            ->assertOk();
    }
}

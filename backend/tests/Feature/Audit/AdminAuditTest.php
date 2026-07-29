<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_super_admin_sees_activity_across_every_tenant(): void
    {
        $superAdmin = $this->superAdmin();
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->postJson('/api/v1/customers', ['name' => 'Tenant A Customer'])->assertCreated();
        $this->actingAsUser($ownerB)->postJson('/api/v1/customers', ['name' => 'Tenant B Customer'])->assertCreated();

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/audit/activity')
            ->assertOk();

        $causerIds = collect($response->json('data'))->pluck('causer.id');
        $this->assertTrue($causerIds->contains($ownerA->id));
        $this->assertTrue($causerIds->contains($ownerB->id));
    }

    public function test_super_admin_can_filter_by_tenant_id(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)->postJson('/api/v1/customers', ['name' => 'Tenant A Customer'])->assertCreated();
        $this->actingAsUser($ownerB)->postJson('/api/v1/customers', ['name' => 'Tenant B Customer'])->assertCreated();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/activity?tenant_id={$tenantA->id}")
            ->assertOk();

        $causerIds = collect($response->json('data'))->pluck('causer.id');
        $this->assertTrue($causerIds->contains($ownerA->id));
        $this->assertFalse($causerIds->contains($ownerB->id));
    }

    public function test_a_regular_tenant_user_cannot_access_admin_audit_routes(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/admin/audit/activity')
            ->assertForbidden();
    }

    public function test_super_admin_sees_login_history_across_tenants(): void
    {
        $superAdmin = $this->superAdmin();
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/audit/login-history')
            ->assertOk();

        $emails = collect($response->json('data'))->pluck('properties.email');
        $this->assertTrue($emails->contains($owner->email));
    }
}

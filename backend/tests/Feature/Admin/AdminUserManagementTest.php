<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_super_admin_can_list_a_tenants_users(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->addUserToTenant($tenant, TenantRole::Photographer);

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/users")
            ->assertOk();

        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains($owner->email));
        $this->assertCount(2, $emails);
    }

    public function test_super_admin_can_deactivate_a_non_owner_employee(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/users/{$photographer->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_deactivating_the_tenants_last_active_owner_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/users/{$owner->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('code', 'CANNOT_DEACTIVATE_LAST_OWNER');
    }

    public function test_super_admin_can_reactivate_a_deactivated_employee(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        $photographer->update(['status' => 'inactive']);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/users/{$photographer->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_super_admin_can_send_a_password_reset_link(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/users/{$owner->id}/reset-password")
            ->assertOk();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $owner->email]);
    }

    public function test_a_non_super_admin_cannot_manage_tenant_users(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/users")
            ->assertForbidden();

        $this->actingAsUser($owner)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/users/{$photographer->id}/deactivate")
            ->assertForbidden();
    }

    /**
     * The one real new bug surface this feature introduces: admin routes
     * carry no `tenant` middleware, so a user's route-model-binding isn't
     * scoped by TenantScope — acting on a {tenant}/{user} pair where the
     * user actually belongs to a DIFFERENT tenant must 404, not succeed.
     */
    public function test_acting_on_a_user_from_a_different_tenant_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenantA->id}/users/{$ownerB->id}/deactivate")
            ->assertStatus(404);

        $this->assertDatabaseHas('users', ['id' => $ownerB->id, 'status' => 'active']);
    }
}

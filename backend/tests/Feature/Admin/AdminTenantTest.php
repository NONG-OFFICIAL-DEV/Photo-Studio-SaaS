<?php

namespace Tests\Feature\Admin;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_super_admin_sees_tenants_across_the_whole_platform(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenantA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/tenants')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($tenantA->id));
        $this->assertTrue($ids->contains($tenantB->id));
    }

    public function test_it_searches_tenants_by_name(): void
    {
        $superAdmin = $this->superAdmin();
        $tenant = Tenant::factory()->create(['name' => 'Findable Studio Ltd']);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/tenants?search=Findable')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Findable Studio Ltd'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_it_filters_tenants_by_status(): void
    {
        $superAdmin = $this->superAdmin();
        $active = Tenant::factory()->create(['is_active' => true]);
        $suspended = Tenant::factory()->create(['is_active' => false]);

        $response = $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/tenants?status=suspended')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($suspended->id));
        $this->assertFalse($ids->contains($active->id));
    }

    public function test_it_shows_a_single_tenant_with_its_subscription(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.subscription.status', 'active');
    }

    public function test_it_suspends_and_reactivates_a_tenant(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => false]);

        $this->actingAsUser($superAdmin)
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'is_active' => true]);
    }

    public function test_a_suspended_tenants_users_are_locked_out(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['is_active' => false]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/customers')
            ->assertForbidden();
    }
}

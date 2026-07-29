<?php

namespace Tests\Feature\Package;

use App\Enums\TenantRole;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PackagePermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_viewer_can_view_but_not_create_packages(): void
    {
        [$tenant, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($viewer)->getJson("/api/v1/packages/{$package->id}")->assertOk();

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/packages', ['name' => 'X', 'components' => []])
            ->assertForbidden();
    }

    public function test_photographer_can_view_but_not_manage_packages(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)->getJson('/api/v1/packages')->assertOk();

        $this->actingAsUser($photographer)
            ->deleteJson("/api/v1/packages/{$package->id}")
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_package(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/packages/{$packageB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_package_list_never_includes_another_tenants_packages(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Package::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Package::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_service_id_from_another_tenant_is_rejected_as_a_component(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $serviceB = \App\Models\Service::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/packages', [
                'name' => 'Cross Tenant Package',
                'components' => [['service_id' => $serviceB->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }
}

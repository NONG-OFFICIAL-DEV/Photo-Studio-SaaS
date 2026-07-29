<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServicePermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_viewer_cannot_create_a_service(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/services', ['name' => 'Should Fail', 'price' => 10, 'pricing_unit' => 'fixed'])
            ->assertForbidden();
    }

    public function test_viewer_can_list_services(): void
    {
        [$tenant, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);
        Service::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($viewer)
            ->getJson('/api/v1/services')
            ->assertOk();
    }

    public function test_photographer_cannot_update_a_service(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->putJson("/api/v1/services/{$service->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_photographer_cannot_delete_a_service(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->deleteJson("/api/v1/services/{$service->id}")
            ->assertForbidden();
    }

    public function test_cashier_cannot_create_a_category(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->postJson('/api/v1/services/categories', ['name' => 'Should Fail'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_service(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/services/{$serviceB->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_update_another_tenants_service(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Original']);

        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/services/{$serviceB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('services', ['id' => $serviceB->id, 'name' => 'Original']);
    }

    public function test_a_tenants_service_list_never_includes_another_tenants_services(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Service::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Service::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_category_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $categoryB = $this->actingAsUser($ownerB)
            ->postJson('/api/v1/services/categories', ['name' => 'Tenant B Category'])
            ->json('data');

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/services', [
                'category_id' => $categoryB['id'],
                'name' => 'Cross Tenant Attempt',
                'price' => 10,
                'pricing_unit' => 'fixed',
            ])
            ->assertStatus(422);
    }
}

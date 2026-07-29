<?php

namespace Tests\Feature\Package;

use App\Enums\TenantRole;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceAddOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PackageCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_a_package_with_components(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 800]);
        $addon = ServiceAddOn::factory()->create(['tenant_id' => $tenant->id, 'price' => 150]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Wedding Package',
                'components' => [
                    ['service_id' => $service->id, 'quantity' => 1],
                    ['addon_id' => $addon->id, 'quantity' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Wedding Package')
            ->assertJsonPath('data.component_total', 1100)
            ->assertJsonPath('data.final_price', 1100);

        $this->assertDatabaseHas('packages', ['id' => $response->json('data.id'), 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('package_components', 2);
    }

    public function test_a_component_needs_exactly_one_of_service_id_or_addon_id(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);
        $addon = ServiceAddOn::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Bad Package',
                'components' => [['service_id' => $service->id, 'addon_id' => $addon->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Bad Package',
                'components' => [['quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_it_lists_packages_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Package::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_updates_a_packages_components(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/packages/{$package->id}", [
                'name' => 'Renamed Package',
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Package')
            ->assertJsonPath('data.component_total', 500);

        $this->assertDatabaseCount('package_components', 1);
    }

    public function test_it_deletes_a_package(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $package = Package::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/packages/{$package->id}")
            ->assertOk();

        $this->assertSoftDeleted('packages', ['id' => $package->id]);
    }

    public function test_name_and_components_are_required(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [])
            ->assertStatus(422);
    }
}

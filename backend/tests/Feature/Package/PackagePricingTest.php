<?php

namespace Tests\Feature\Package;

use App\Enums\TenantRole;
use App\Models\Service;
use App\Models\ServiceAddOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PackagePricingTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_optional_components_are_excluded_from_the_component_total(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $included = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 800]);
        $optional = ServiceAddOn::factory()->create(['tenant_id' => $tenant->id, 'price' => 120]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Wedding Package',
                'components' => [
                    ['service_id' => $included->id, 'quantity' => 1, 'is_optional' => false],
                    ['addon_id' => $optional->id, 'quantity' => 1, 'is_optional' => true],
                ],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.component_total', 800)
            ->assertJsonPath('data.final_price', 800);

        $optionalComponent = collect($response->json('data.components'))->firstWhere('addon_id', $optional->id);
        $this->assertTrue($optionalComponent['is_optional']);
    }

    public function test_a_percent_discount_reduces_the_component_total(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Discounted Package',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.component_total', 1000)
            ->assertJsonPath('data.final_price', 850);
    }

    public function test_a_fixed_discount_reduces_the_component_total(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Discounted Package',
                'discount_type' => 'fixed',
                'discount_value' => 250,
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.final_price', 750);
    }

    public function test_a_percent_discount_over_100_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Bad Discount Package',
                'discount_type' => 'percent',
                'discount_value' => 150,
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_a_discount_never_takes_the_price_below_zero(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 100]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Overshot Discount Package',
                'discount_type' => 'fixed',
                'discount_value' => 500,
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.final_price', 0);
    }

    public function test_override_price_wins_over_the_computed_discount(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Custom Priced Package',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'override_price' => 1999,
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.component_total', 1000)
            ->assertJsonPath('data.final_price', 1999);
    }

    public function test_price_updates_live_when_a_components_catalog_price_changes(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);

        $packageId = $this->actingAsUser($owner)
            ->postJson('/api/v1/packages', [
                'name' => 'Live Priced Package',
                'components' => [['service_id' => $service->id, 'quantity' => 1]],
            ])
            ->json('data.id');

        $service->update(['price' => 700]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/packages/{$packageId}")
            ->assertOk()
            ->assertJsonPath('data.component_total', 700)
            ->assertJsonPath('data.final_price', 700);
    }
}

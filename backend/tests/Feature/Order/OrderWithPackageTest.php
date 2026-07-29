<?php

namespace Tests\Feature\Order;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceAddOn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class OrderWithPackageTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_selecting_a_package_snapshots_its_name_and_final_price(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 1000]);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Wedding Package',
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]);
        $package->components()->create(['tenant_id' => $tenant->id, 'service_id' => $service->id, 'quantity' => 1]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customer->id,
                'items' => [['package_id' => $package->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.items.0.package_id', $package->id)
            ->assertJsonPath('data.items.0.name', 'Wedding Package')
            ->assertJsonPath('data.items.0.unit_price', 900)
            ->assertJsonPath('data.subtotal', 900);
    }

    public function test_a_package_can_be_combined_with_its_optional_addon_as_a_separate_line(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 800]);
        $optionalAddon = ServiceAddOn::factory()->create(['tenant_id' => $tenant->id, 'price' => 120]);
        $package = Package::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Portrait Package']);
        $package->components()->create(['tenant_id' => $tenant->id, 'service_id' => $service->id, 'quantity' => 1]);
        $package->components()->create(['tenant_id' => $tenant->id, 'addon_id' => $optionalAddon->id, 'quantity' => 1, 'is_optional' => true]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customer->id,
                'items' => [
                    ['package_id' => $package->id, 'quantity' => 1],
                    ['addon_id' => $optionalAddon->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.subtotal', 920)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_package_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerA = Customer::factory()->create(['tenant_id' => $ownerA->tenant_id]);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/orders', [
                'customer_id' => $customerA->id,
                'items' => [['package_id' => $packageB->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }
}

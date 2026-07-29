<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoiceWithPackageTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_selecting_a_package_snapshots_its_name_and_final_price(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);
        $package = Package::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Portrait Package',
            'override_price' => 450,
        ]);
        $package->components()->create(['tenant_id' => $tenant->id, 'service_id' => $service->id, 'quantity' => 1]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'items' => [['package_id' => $package->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        $response->assertJsonPath('data.items.0.package_id', $package->id)
            ->assertJsonPath('data.items.0.name', 'Portrait Package')
            ->assertJsonPath('data.items.0.unit_price', 450)
            ->assertJsonPath('data.items.0.line_total', 900)
            ->assertJsonPath('data.subtotal', 900);
    }

    public function test_package_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerA = Customer::factory()->create(['tenant_id' => $ownerA->tenant_id]);
        $packageB = Package::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customerA->id,
                'items' => [['package_id' => $packageB->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }
}

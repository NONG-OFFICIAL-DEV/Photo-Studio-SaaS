<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_viewer_cannot_create_a_customer(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/customers', ['name' => 'Should Fail'])
            ->assertForbidden();
    }

    public function test_viewer_can_list_customers(): void
    {
        [$tenant, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);
        Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($viewer)
            ->getJson('/api/v1/customers')
            ->assertOk();
    }

    public function test_photographer_cannot_delete_a_customer(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertForbidden();
    }

    public function test_cashier_cannot_export_customers(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->get('/api/v1/customers/export')
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_customer(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/customers/{$customerB->id}")
            ->assertNotFound();
    }

    public function test_a_tenant_cannot_update_another_tenants_customer(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Original']);

        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/customers/{$customerB->id}", ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('customers', ['id' => $customerB->id, 'name' => 'Original']);
    }

    public function test_a_tenants_customer_list_never_includes_another_tenants_customers(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Customer::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Customer::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_tag_ids_from_another_tenant_are_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $tagB = $this->actingAsUser($ownerB)
            ->postJson('/api/v1/customers/tags', ['name' => 'Tenant B Tag'])
            ->json('data');

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/customers', ['name' => 'Cross Tenant Attempt', 'tag_ids' => [$tagB['id']]])
            ->assertStatus(422);
    }
}

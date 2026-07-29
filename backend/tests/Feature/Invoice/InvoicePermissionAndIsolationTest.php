<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoicePermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_cashier_can_create_send_and_record_payments(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $invoiceId = $this->actingAsUser($cashier)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'items' => [['name' => 'Package', 'unit_price' => 100, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAsUser($cashier)->postJson("/api/v1/invoices/{$invoiceId}/send")->assertOk();

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/invoices/{$invoiceId}/payments", ['amount' => 100, 'method' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_receptionist_can_view_but_not_create_invoices(): void
    {
        [$tenant, $receptionist] = $this->createTenantWithUser(TenantRole::Receptionist);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($receptionist)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk();

        $this->actingAsUser($receptionist)
            ->postJson('/api/v1/invoices', ['customer_id' => $invoice->customer_id, 'items' => [['name' => 'X', 'unit_price' => 1, 'quantity' => 1]]])
            ->assertForbidden();
    }

    public function test_photographer_cannot_record_payments(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", ['amount' => 10, 'method' => 'cash'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_invoice(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/invoices/{$invoiceB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_invoice_list_never_includes_another_tenants_invoices(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Invoice::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Invoice::factory()->count(5)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_customer_id_from_another_tenant_is_rejected(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customerB->id,
                'items' => [['name' => 'X', 'unit_price' => 1, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }
}

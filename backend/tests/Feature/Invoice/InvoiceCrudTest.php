<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_a_manual_invoice_with_computed_totals(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'discount_amount' => 10,
                'tax_rate' => 10,
                'items' => [
                    ['name' => 'Wedding Package', 'unit_price' => 100, 'quantity' => 1],
                    ['name' => 'Extra Print', 'unit_price' => 10, 'quantity' => 2],
                ],
            ])
            ->assertCreated();

        // subtotal = 100 + 20 = 120; taxable = 120 - 10 = 110; tax = 11; total = 121
        $response->assertJsonPath('data.subtotal', 120)
            ->assertJsonPath('data.discount_amount', 10)
            ->assertJsonPath('data.tax_amount', 11)
            ->assertJsonPath('data.total', 121)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.balance_due', 121);

        $this->assertNotEmpty($response->json('data.invoice_number'));
        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('invoice_items', 2);
    }

    public function test_it_creates_an_invoice_from_an_order_by_snapshotting_its_items(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id]);
        OrderItem::factory()->create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'name' => 'Album Package',
            'unit_price' => 200,
            'quantity' => 1,
            'line_total' => 200,
        ]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', ['order_id' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.total', 200)
            ->assertJsonPath('data.items.0.name', 'Album Package');

        $this->assertDatabaseHas('invoices', ['id' => $response->json('data.id'), 'order_id' => $order->id]);
    }

    public function test_items_or_order_id_is_required(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/invoices', ['customer_id' => $customer->id])
            ->assertStatus(422);
    }

    public function test_it_updates_a_draft_invoices_items(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'items' => [['name' => 'New Line', 'unit_price' => 50, 'quantity' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.total', 100);
    }

    public function test_items_cannot_be_changed_once_sent(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'items' => [['name' => 'New Line', 'unit_price' => 50, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_only_draft_invoices_can_be_deleted(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $sent = Invoice::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)->deleteJson("/api/v1/invoices/{$sent->id}")->assertStatus(422);
        $this->actingAsUser($owner)->deleteJson("/api/v1/invoices/{$draft->id}")->assertOk();

        $this->assertSoftDeleted('invoices', ['id' => $draft->id]);
    }

    public function test_it_lists_invoices_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Invoice::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}

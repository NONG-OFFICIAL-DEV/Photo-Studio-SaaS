<?php

namespace Tests\Feature\Branch;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * `branch_id` is a soft filter, not a security boundary — these confirm
 * every list endpoint that now has a branch_id column can actually be
 * filtered by it (several of these columns, e.g. bookings, existed before
 * this feature but weren't filterable at all).
 */
class BranchFilteringTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_bookings_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $inA = Booking::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id]);
        Booking::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id]);

        $response = $this->actingAsUser($owner)->getJson("/api/v1/bookings?branch_id={$branchA->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }

    public function test_inventory_items_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $inA = InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id]);
        InventoryItem::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id]);

        $response = $this->actingAsUser($owner)->getJson("/api/v1/inventory-items?branch_id={$branchA->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }

    public function test_orders_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $inA = Order::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id]);
        Order::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id]);

        $response = $this->actingAsUser($owner)->getJson("/api/v1/orders?branch_id={$branchA->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }

    public function test_invoices_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $inA = Invoice::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id]);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id]);

        $response = $this->actingAsUser($owner)->getJson("/api/v1/invoices?branch_id={$branchA->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }

    /**
     * Payments have no standalone list route (only nested create/delete
     * under an invoice) — the branch_id filter is exercised directly at
     * the repository, same coverage in spirit as the HTTP-level filter
     * tests above.
     */
    public function test_payments_can_be_filtered_by_branch(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $inA = Payment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'branch_id' => $branchA->id]);
        Payment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'branch_id' => $branchB->id]);

        $results = app(PaymentRepositoryInterface::class)->paginateServer(['branch_id' => $branchA->id]);

        $this->assertCount(1, $results->items());
        $this->assertSame($inA->id, $results->items()[0]->id);
    }

    public function test_expenses_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $inA = Expense::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id]);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id]);

        $response = $this->actingAsUser($owner)->getJson("/api/v1/expenses?branch_id={$branchA->id}")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($inA->id, $response->json('data.0.id'));
    }
}

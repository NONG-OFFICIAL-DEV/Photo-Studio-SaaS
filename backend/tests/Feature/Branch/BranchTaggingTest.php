<?php

namespace Tests\Feature\Branch;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BranchTaggingTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    // ── Booking ──────────────────────────────────────────────────────────

    public function test_booking_branch_id_stays_null_when_the_tenant_has_no_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', $this->bookingPayload($customer->id));

        $response->assertCreated()->assertJsonPath('data.branch_id', null);
    }

    public function test_booking_branch_id_is_auto_assigned_when_the_tenant_has_exactly_one_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', $this->bookingPayload($customer->id));

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_booking_requires_a_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', $this->bookingPayload($customer->id));

        $response->assertStatus(422)->assertJsonPath('code', 'BRANCH_REQUIRED');
    }

    public function test_booking_can_be_created_with_an_explicit_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        $downtown = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', [
            ...$this->bookingPayload($customer->id),
            'branch_id' => $downtown->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.branch_id', $downtown->id);
    }

    public function test_booking_branch_id_is_auto_assigned_to_the_only_active_branch_when_another_is_inactive(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $active = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Closed Branch', 'is_active' => false]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', $this->bookingPayload($customer->id));

        $response->assertCreated()->assertJsonPath('data.branch_id', $active->id);
    }

    public function test_booking_rejects_a_branch_id_belonging_to_another_tenant(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        [$otherTenant] = $this->createTenantWithUser(TenantRole::Owner);
        $otherBranch = Branch::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Studio Branch']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/bookings', [
            ...$this->bookingPayload($customer->id),
            'branch_id' => $otherBranch->id,
        ]);

        $response->assertStatus(422)->assertJsonPath('meta.errors.branch_id.0', 'The selected branch id is invalid.');
    }

    private function bookingPayload(string $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'type' => 'wedding',
            'title' => 'Branch Tagging Test Booking',
            'location_type' => 'studio',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHours(2)->toIso8601String(),
        ];
    }

    // ── InventoryItem ────────────────────────────────────────────────────

    public function test_inventory_item_branch_id_stays_null_when_the_tenant_has_no_branches(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/inventory-items', $this->inventoryItemPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', null);
    }

    public function test_inventory_item_branch_id_is_auto_assigned_when_the_tenant_has_exactly_one_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/inventory-items', $this->inventoryItemPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_inventory_item_requires_a_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/inventory-items', $this->inventoryItemPayload());

        $response->assertStatus(422)->assertJsonPath('code', 'BRANCH_REQUIRED');
    }

    public function test_inventory_item_branch_id_is_auto_assigned_to_the_only_active_branch_when_another_is_inactive(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $active = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Closed Branch', 'is_active' => false]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/inventory-items', $this->inventoryItemPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', $active->id);
    }

    private function inventoryItemPayload(): array
    {
        return ['name' => 'Photo Paper (A4)', 'unit' => 'sheet'];
    }

    // ── Staff (User) ─────────────────────────────────────────────────────

    public function test_staff_branch_id_stays_null_when_the_tenant_has_no_branches(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/users', $this->staffPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', null);
    }

    public function test_staff_branch_id_is_auto_assigned_when_the_tenant_has_exactly_one_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/users', $this->staffPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_staff_requires_a_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/users', $this->staffPayload());

        $response->assertStatus(422)->assertJsonPath('code', 'BRANCH_REQUIRED');
    }

    public function test_staff_branch_id_is_auto_assigned_to_the_only_active_branch_when_another_is_inactive(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $active = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Closed Branch', 'is_active' => false]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/users', $this->staffPayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', $active->id);
    }

    private function staffPayload(): array
    {
        return [
            'name' => 'New Photographer',
            'email' => 'branch-staff@example.test',
            'password' => 'password123',
            'role' => TenantRole::Photographer->value,
        ];
    }

    // ── Order (inherits from its booking, or resolved standalone) ────────

    public function test_order_inherits_its_branch_from_the_linked_booking(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        $otherBranch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);
        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            // Even if the client somehow sends a different branch_id, the
            // booking's branch always wins for a booking-linked order.
            'branch_id' => $otherBranch->id,
            'items' => [['name' => 'Wedding Package', 'unit_price' => 500, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_a_standalone_order_requires_a_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['name' => 'Wedding Package', 'unit_price' => 500, 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonPath('code', 'BRANCH_REQUIRED');
    }

    public function test_a_standalone_order_branch_id_is_auto_assigned_when_the_tenant_has_exactly_one_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['name' => 'Wedding Package', 'unit_price' => 500, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    // ── Invoice (always inherits from its order) ──────────────────────────

    public function test_invoice_inherits_its_branch_from_the_linked_order(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        $order = Order::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'branch_id' => $branch->id]);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'items' => [['name' => 'Wedding Package', 'unit_price' => 500, 'quantity' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    // ── Payment (always inherits from its invoice) ────────────────────────

    public function test_payment_inherits_its_branch_from_the_invoice(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'status' => InvoiceStatus::Sent,
            'total' => 100,
        ]);

        $response = $this->actingAsUser($owner)->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 50,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'branch_id' => $branch->id]);
    }

    // ── Expense (standalone, resolved the same way Booking/User are) ─────

    public function test_expense_branch_id_stays_null_when_the_tenant_has_no_branches(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/expenses', $this->expensePayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', null);
    }

    public function test_expense_branch_id_is_auto_assigned_when_the_tenant_has_exactly_one_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/expenses', $this->expensePayload());

        $response->assertCreated()->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_expense_requires_a_branch_when_the_tenant_has_multiple_branches(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Studio']);
        Branch::create(['tenant_id' => $tenant->id, 'name' => 'Downtown Studio']);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/expenses', $this->expensePayload());

        $response->assertStatus(422)->assertJsonPath('code', 'BRANCH_REQUIRED');
    }

    private function expensePayload(): array
    {
        return [
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ];
    }
}

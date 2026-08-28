<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ReportBranchFilterTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_revenue_report_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);

        $invoiceA = Invoice::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'issue_date' => '2026-07-05', 'total' => 500]);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'issue_date' => '2026-07-05', 'total' => 300]);
        Payment::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'invoice_id' => $invoiceA->id, 'paid_at' => '2026-07-10', 'amount' => 200]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/reports/revenue?date_from=2026-07-01&date_to=2026-07-31&branch_id={$branchA->id}")
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', 500)
            ->assertJsonPath('data.total_collected', 200);
    }

    public function test_bookings_report_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);

        Booking::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'starts_at' => '2026-07-05 10:00:00']);
        Booking::factory()->count(2)->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'starts_at' => '2026-07-05 10:00:00']);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/reports/bookings?date_from=2026-07-01&date_to=2026-07-31&branch_id={$branchA->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_orders_report_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);

        Order::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'total' => 100]);
        Order::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'total' => 250]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/reports/orders?branch_id={$branchA->id}")
            ->assertOk()
            ->assertJsonPath('data.total_count', 1)
            ->assertJsonPath('data.total_value', 100);
    }

    public function test_expenses_report_can_be_filtered_by_branch(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $branchA = Branch::create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $branchB = Branch::create(['tenant_id' => $tenant->id, 'name' => 'B']);

        Expense::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchA->id, 'amount' => 40, 'expense_date' => now()]);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branchB->id, 'amount' => 60, 'expense_date' => now()]);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/reports/expenses?branch_id={$branchA->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 40);
    }
}

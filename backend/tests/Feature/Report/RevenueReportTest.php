<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_computes_invoiced_collected_and_outstanding_within_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-07-05', 'total' => 500]);
        $invoiceForPayment = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-07-20', 'total' => 300]);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-06-30', 'total' => 999]);

        // Explicit invoice_id — PaymentFactory's default nested Invoice would
        // otherwise pick up today's real date, which can land inside this
        // test's own July 2026 window and pollute total_invoiced.
        Payment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoiceForPayment->id, 'paid_at' => '2026-07-10', 'amount' => 200]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/revenue?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', 800)
            ->assertJsonPath('data.total_collected', 200)
            ->assertJsonPath('data.outstanding', 600);
    }

    public function test_breakdown_groups_by_day_for_a_short_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-07-05', 'total' => 100]);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-07-05', 'total' => 150]);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/revenue?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk();

        $row = collect($response->json('data.breakdown'))->firstWhere('period', '2026-07-05');
        $this->assertNotNull($row);
        $this->assertEquals(250, $row['invoiced']);
    }

    public function test_a_tenants_revenue_report_never_includes_another_tenants_data(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        Invoice::factory()->create(['tenant_id' => $tenantB->id, 'issue_date' => '2026-07-05', 'total' => 9999]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/reports/revenue?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', 0);
    }
}

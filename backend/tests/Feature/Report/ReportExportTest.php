<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Expense;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    #[DataProvider('exportEndpoints')]
    public function test_it_downloads_each_report_as_xlsx(string $endpoint): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->seedReportData($tenant->id);

        $this->actingAsUser($owner)
            ->get("/api/v1/reports/{$endpoint}/export")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[DataProvider('exportEndpoints')]
    public function test_it_downloads_each_report_as_csv(string $endpoint): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->seedReportData($tenant->id);

        $this->actingAsUser($owner)
            ->get("/api/v1/reports/{$endpoint}/export?format=csv")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public static function exportEndpoints(): array
    {
        return [
            ['revenue'],
            ['bookings'],
            ['orders'],
            ['expenses'],
        ];
    }

    public function test_viewer_cannot_export_reports(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->get('/api/v1/reports/revenue/export')
            ->assertForbidden();
    }

    /**
     * Revenue/expense breakdown rows only exist when there's matching data
     * (unlike bookings/orders, which always emit one row per status) — an
     * empty CSV can get MIME-sniffed as text/plain instead of text/csv, so
     * every export test seeds at least one real row per report.
     */
    protected function seedReportData(string $tenantId): void
    {
        Invoice::factory()->create(['tenant_id' => $tenantId, 'issue_date' => now()->toDateString(), 'total' => 100]);
        Expense::factory()->create(['tenant_id' => $tenantId, 'expense_date' => now()->toDateString(), 'amount' => 50]);
    }
}

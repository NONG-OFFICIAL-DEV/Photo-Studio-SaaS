<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_breaks_down_expenses_by_category_within_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $rent = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Rent']);

        Expense::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $rent->id, 'amount' => 1200, 'expense_date' => '2026-07-01']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'category_id' => null, 'amount' => 50, 'expense_date' => '2026-07-15']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'category_id' => $rent->id, 'amount' => 999, 'expense_date' => '2026-06-01']);

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/expenses?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.total', 1250);

        $rentRow = collect($response->json('data.by_category'))->firstWhere('category', 'Rent');
        $this->assertEquals(1200, $rentRow['amount']);

        $uncategorizedRow = collect($response->json('data.by_category'))->firstWhere('category', 'Uncategorized');
        $this->assertEquals(50, $uncategorizedRow['amount']);

        $breakdown = collect($response->json('data.breakdown'))->keyBy('period');
        $this->assertEquals(1200, $breakdown['2026-07-01']['total']);
        $this->assertEquals(50, $breakdown['2026-07-15']['total']);
        // The June expense is outside the range and must not leak into it.
        $this->assertFalse($breakdown->has('2026-06-01'));
    }
}

<?php

namespace Tests\Feature\Expense;

use App\Enums\TenantRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_an_expense(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/expenses', [
                'category_id' => $category->id,
                'amount' => 250.50,
                'expense_date' => now()->toDateString(),
                'vendor' => 'Acme Supplies',
                'payment_method' => 'bank_transfer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 250.5)
            ->assertJsonPath('data.vendor', 'Acme Supplies')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('expenses', ['id' => $response->json('data.id'), 'tenant_id' => $tenant->id]);
    }

    public function test_it_lists_expenses_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Expense::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_filters_expenses_by_date_range(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-01-15']);
        Expense::factory()->create(['tenant_id' => $tenant->id, 'expense_date' => '2026-06-15']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/expenses?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_updates_an_expense(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id, 'amount' => 100]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/expenses/{$expense->id}", ['amount' => 175.25])
            ->assertOk()
            ->assertJsonPath('data.amount', 175.25);
    }

    public function test_it_deletes_an_expense(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/expenses/{$expense->id}")
            ->assertOk();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_amount_expense_date_and_payment_method_are_required(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/expenses', [])
            ->assertStatus(422);
    }
}

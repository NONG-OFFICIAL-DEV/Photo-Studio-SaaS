<?php

namespace Tests\Feature\Expense;

use App\Enums\TenantRole;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ExpensePermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_cashier_can_create_and_update_but_not_delete_expenses(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $expense = $this->actingAsUser($cashier)
            ->postJson('/api/v1/expenses', [
                'amount' => 50,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsUser($cashier)
            ->putJson("/api/v1/expenses/{$expense['id']}", ['amount' => 75])
            ->assertOk();

        $this->actingAsUser($cashier)
            ->deleteJson("/api/v1/expenses/{$expense['id']}")
            ->assertForbidden();
    }

    public function test_photographer_cannot_view_or_create_expenses(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $expense = Expense::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->getJson("/api/v1/expenses/{$expense->id}")
            ->assertForbidden();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/expenses', ['amount' => 10, 'expense_date' => now()->toDateString(), 'payment_method' => 'cash'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_expense(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $expenseB = Expense::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/expenses/{$expenseB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_expense_list_never_includes_another_tenants_expenses(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Expense::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Expense::factory()->count(4)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}

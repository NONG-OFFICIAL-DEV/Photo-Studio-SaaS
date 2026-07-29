<?php

namespace Tests\Feature\Expense;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_lists_updates_and_deletes_categories(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $category = $this->postJson('/api/v1/expenses/categories', ['name' => 'Rent'])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/expenses/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Rent']);

        $this->putJson("/api/v1/expenses/categories/{$category['id']}", ['name' => 'Studio Rent'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Studio Rent');

        $this->deleteJson("/api/v1/expenses/categories/{$category['id']}")
            ->assertOk();

        $this->assertSoftDeleted('expense_categories', ['id' => $category['id']]);
    }

    public function test_category_names_are_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/expenses/categories', ['name' => 'Utilities'])->assertCreated();

        $this->postJson('/api/v1/expenses/categories', ['name' => 'Utilities'])
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Branch;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_lists_updates_and_deletes_branches(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $branch = $this->postJson('/api/v1/branches', ['name' => 'Downtown Studio', 'address' => '123 Main St'])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Downtown Studio']);

        $this->putJson("/api/v1/branches/{$branch['id']}", ['name' => 'Downtown Studio (Renamed)'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Downtown Studio (Renamed)');

        $this->deleteJson("/api/v1/branches/{$branch['id']}")
            ->assertOk();

        $this->assertSoftDeleted('branches', ['id' => $branch['id']]);
    }

    public function test_branch_names_are_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/branches', ['name' => 'Main Branch'])->assertCreated();

        $this->postJson('/api/v1/branches', ['name' => 'Main Branch'])
            ->assertStatus(422);
    }

    public function test_a_user_without_branches_create_cannot_create_a_branch(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->postJson('/api/v1/branches', ['name' => 'New Branch'])
            ->assertForbidden();
    }

    public function test_creating_a_branch_is_blocked_once_the_plans_max_branches_limit_is_reached(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['max_branches' => 1]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/branches', ['name' => 'First Branch'])
            ->assertCreated();

        $this->actingAsUser($owner)
            ->postJson('/api/v1/branches', ['name' => 'One Too Many'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'BRANCH_LIMIT_REACHED');

        $this->assertDatabaseMissing('branches', ['name' => 'One Too Many']);
    }

    public function test_a_branch_can_be_created_when_under_the_max_branches_limit(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['max_branches' => 5]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/branches', ['name' => 'Room To Grow'])
            ->assertCreated();
    }
}

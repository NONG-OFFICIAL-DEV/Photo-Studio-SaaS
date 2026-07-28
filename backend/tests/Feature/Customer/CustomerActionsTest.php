<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerActionsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_toggles_favorite(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_it_blacklists_and_unblacklists_with_a_reason(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/blacklist", ['reason' => 'Bounced check'])
            ->assertOk()
            ->assertJsonPath('data.is_blacklisted', true)
            ->assertJsonPath('data.blacklist_reason', 'Bounced check');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/unblacklist")
            ->assertOk()
            ->assertJsonPath('data.is_blacklisted', false)
            ->assertJsonPath('data.blacklist_reason', null);
    }

    public function test_blacklisting_requires_a_reason(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/blacklist", [])
            ->assertStatus(422);
    }

    public function test_notes_can_be_added_and_deleted(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $note = $this->actingAsUser($owner)
            ->postJson("/api/v1/customers/{$customer->id}/notes", ['note' => 'Prefers outdoor shoots'])
            ->assertCreated()
            ->assertJsonPath('data.note', 'Prefers outdoor shoots')
            ->assertJsonPath('data.author', $owner->name)
            ->json('data');

        $this->assertDatabaseHas('customer_notes', ['id' => $note['id'], 'customer_id' => $customer->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/customers/{$customer->id}/notes/{$note['id']}")
            ->assertOk();

        $this->assertSoftDeleted('customer_notes', ['id' => $note['id']]);
    }

    public function test_customer_edits_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Before Name']);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/customers/{$customer->id}", ['name' => 'After Name']);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $customer->id,
            'log_name' => 'customer',
            'tenant_id' => $tenant->id,
        ]);
    }
}

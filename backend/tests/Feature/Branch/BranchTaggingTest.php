<?php

namespace Tests\Feature\Branch;

use App\Enums\TenantRole;
use App\Models\Branch;
use App\Models\Customer;
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

    private function staffPayload(): array
    {
        return [
            'name' => 'New Photographer',
            'email' => 'branch-staff@example.test',
            'password' => 'password123',
            'role' => TenantRole::Photographer->value,
        ];
    }
}

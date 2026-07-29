<?php

namespace Tests\Feature\Commission;

use App\Enums\TenantRole;
use App\Models\CommissionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CommissionEntryPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_photographer_cannot_view_or_record_commissions(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/commission-entries')
            ->assertForbidden();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/commission-entries', ['user_id' => $photographer->id, 'amount' => 10, 'earned_date' => '2026-07-01'])
            ->assertForbidden();
    }

    public function test_a_tenants_commission_list_never_includes_another_tenants_entries(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        CommissionEntry::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        CommissionEntry::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/commission-entries')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}

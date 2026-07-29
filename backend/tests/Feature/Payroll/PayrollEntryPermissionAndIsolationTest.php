<?php

namespace Tests\Feature\Payroll;

use App\Enums\TenantRole;
use App\Models\PayrollEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PayrollEntryPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_cashier_cannot_view_or_create_payroll_entries(): void
    {
        [$tenant, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);
        $employee = $this->addUserToTenant($tenant, TenantRole::Receptionist);

        $this->actingAsUser($cashier)
            ->getJson('/api/v1/payroll-entries')
            ->assertForbidden();

        $this->actingAsUser($cashier)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_payroll_entry(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $entryB = PayrollEntry::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/payroll-entries/{$entryB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_payroll_list_never_includes_another_tenants_entries(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        PayrollEntry::factory()->create(['tenant_id' => $tenantA->id]);
        PayrollEntry::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/payroll-entries')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}

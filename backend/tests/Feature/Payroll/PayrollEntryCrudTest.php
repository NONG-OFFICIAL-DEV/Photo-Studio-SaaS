<?php

namespace Tests\Feature\Payroll;

use App\Enums\TenantRole;
use App\Models\AttendanceRecord;
use App\Models\CommissionEntry;
use App\Models\PayrollEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class PayrollEntryCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_computes_base_pay_flat_for_a_salaried_employee(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Receptionist, ['pay_type' => 'salary', 'base_pay' => 1500]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.base_pay', 1500)
            ->assertJsonPath('data.net_pay', 1500);
    }

    public function test_it_computes_base_pay_from_attendance_hours_for_an_hourly_employee(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer, ['pay_type' => 'hourly', 'base_pay' => 20]);

        AttendanceRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $employee->id,
            'date' => '2026-07-10',
            'clock_in_at' => '2026-07-10 09:00:00',
            'clock_out_at' => '2026-07-10 17:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $employee->id,
            'date' => '2026-07-11',
            'clock_in_at' => '2026-07-11 09:00:00',
            'clock_out_at' => '2026-07-11 13:00:00',
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.base_pay', 240); // (8 + 4) hours * $20
    }

    public function test_it_sums_commission_entries_within_the_period(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer, ['pay_type' => 'salary', 'base_pay' => 1000]);

        CommissionEntry::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'amount' => 50, 'earned_date' => '2026-07-05']);
        CommissionEntry::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'amount' => 75, 'earned_date' => '2026-07-20']);
        CommissionEntry::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'amount' => 999, 'earned_date' => '2026-06-01']);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertCreated()
            ->assertJsonPath('data.commission_total', 125)
            ->assertJsonPath('data.net_pay', 1125);
    }

    public function test_deductions_reduce_net_pay(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Receptionist, ['pay_type' => 'salary', 'base_pay' => 1000]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'deductions' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.net_pay', 900);
    }

    public function test_cannot_create_a_second_entry_for_the_same_employee_and_period(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Receptionist);
        PayrollEntry::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $employee->id,
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
        ]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/payroll-entries', [
                'user_id' => $employee->id,
                'period_label' => 'July 2026 (again)',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
            ])
            ->assertStatus(422);
    }

    public function test_marking_paid_locks_further_edits_and_deletion(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $entry = PayrollEntry::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/payroll-entries/{$entry->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->actingAsUser($owner)
            ->putJson("/api/v1/payroll-entries/{$entry->id}", ['deductions' => 50])
            ->assertStatus(422);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/payroll-entries/{$entry->id}")
            ->assertStatus(422);
    }

    public function test_cannot_pay_an_already_paid_entry(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $entry = PayrollEntry::factory()->create(['tenant_id' => $tenant->id, 'status' => 'paid']);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/payroll-entries/{$entry->id}/pay")
            ->assertStatus(422);
    }

    public function test_draft_entries_can_be_updated_and_deleted(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $entry = PayrollEntry::factory()->create(['tenant_id' => $tenant->id, 'base_pay' => 1000, 'net_pay' => 1000]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/payroll-entries/{$entry->id}", ['deductions' => 100])
            ->assertOk()
            ->assertJsonPath('data.net_pay', 900);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/payroll-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('payroll_entries', ['id' => $entry->id]);
    }
}

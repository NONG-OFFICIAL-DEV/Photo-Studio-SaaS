<?php

namespace Tests\Feature\Attendance;

use App\Enums\TenantRole;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AttendanceCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_manager_can_mark_an_employee_absent(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/attendance', [
                'user_id' => $photographer->id,
                'date' => '2026-08-01',
                'status' => 'absent',
                'reason' => 'Sick leave',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'absent')
            ->assertJsonPath('data.reason', 'Sick leave');
    }

    public function test_cannot_create_a_second_record_for_the_same_employee_and_date(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        AttendanceRecord::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $photographer->id, 'date' => '2026-08-01']);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/attendance', [
                'user_id' => $photographer->id,
                'date' => '2026-08-01',
                'status' => 'absent',
            ])
            ->assertStatus(422);
    }

    public function test_it_lists_records_with_pagination_and_filters(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);
        AttendanceRecord::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $photographer->id, 'date' => '2026-08-01', 'status' => 'present']);
        AttendanceRecord::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $photographer->id, 'date' => '2026-08-02', 'status' => 'absent']);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/attendance?status=absent')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_manager_can_update_a_record(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $record = AttendanceRecord::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/attendance/{$record->id}", ['status' => 'late'])
            ->assertOk()
            ->assertJsonPath('data.status', 'late');
    }

    public function test_manager_can_delete_a_record(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $record = AttendanceRecord::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/attendance/{$record->id}")
            ->assertOk();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }
}

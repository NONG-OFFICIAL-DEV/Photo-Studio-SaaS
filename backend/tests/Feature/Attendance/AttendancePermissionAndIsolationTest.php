<?php

namespace Tests\Feature\Attendance;

use App\Enums\TenantRole;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AttendancePermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_photographer_cannot_view_the_team_attendance_list_or_mark_others_absent(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $other = $this->addUserToTenant($tenant, TenantRole::Editor);

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/attendance')
            ->assertForbidden();

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/attendance', ['user_id' => $other->id, 'date' => '2026-08-01', 'status' => 'absent'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_attendance_records(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        AttendanceRecord::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/attendance')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}

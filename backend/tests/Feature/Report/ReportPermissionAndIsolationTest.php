<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ReportPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_manager_and_cashier_can_view_reports(): void
    {
        [, $manager] = $this->createTenantWithUser(TenantRole::Manager);
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($manager)->getJson('/api/v1/reports/revenue')->assertOk();
        $this->actingAsUser($cashier)->getJson('/api/v1/reports/revenue')->assertOk();
    }

    public function test_photographer_editor_receptionist_and_viewer_cannot_view_reports(): void
    {
        foreach ([TenantRole::Photographer, TenantRole::Editor, TenantRole::Receptionist, TenantRole::Viewer] as $role) {
            [, $user] = $this->createTenantWithUser($role);

            $this->actingAsUser($user)
                ->getJson('/api/v1/reports/revenue')
                ->assertForbidden();
        }
    }
}

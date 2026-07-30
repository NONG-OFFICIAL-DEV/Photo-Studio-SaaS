<?php

namespace Tests\Feature\Report;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ReportPlanFeatureGateTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_reports_are_blocked_when_the_plan_does_not_include_the_feature(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['has_reports' => false]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/revenue')
            ->assertStatus(403);
    }

    public function test_reports_are_reachable_when_the_plan_includes_the_feature(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['has_reports' => true]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/reports/revenue')
            ->assertOk();
    }
}

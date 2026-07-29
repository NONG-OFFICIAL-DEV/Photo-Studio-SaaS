<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServicePriceHistoryTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_price_changes_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'price' => 500]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/services/{$service->id}", ['price' => 600]);

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $service->id,
            'log_name' => 'service',
            'tenant_id' => $tenant->id,
        ]);
    }
}

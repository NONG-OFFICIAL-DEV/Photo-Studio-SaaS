<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServiceAddOnTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_lists_updates_and_deletes_addons(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $addon = $this->postJson('/api/v1/services/addons', ['name' => 'Extra Hour', 'price' => 50])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/services/addons')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Extra Hour']);

        $this->putJson("/api/v1/services/addons/{$addon['id']}", ['name' => 'Extra Hour', 'price' => 75])
            ->assertOk()
            ->assertJsonPath('data.price', 75);

        $this->deleteJson("/api/v1/services/addons/{$addon['id']}")
            ->assertOk();

        $this->assertSoftDeleted('service_addons', ['id' => $addon['id']]);
    }

    public function test_addon_names_are_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/services/addons', ['name' => 'Extra Hour', 'price' => 50])->assertCreated();

        $this->postJson('/api/v1/services/addons', ['name' => 'Extra Hour', 'price' => 60])
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Album;

use App\Enums\TenantRole;
use App\Models\Album;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AlbumCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_an_album(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/albums', [
                'name' => 'Smith Wedding Album',
                'customer_id' => $customer->id,
                'expected_photo_count' => 80,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Smith Wedding Album')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->assertDatabaseHas('albums', ['name' => 'Smith Wedding Album', 'tenant_id' => $tenant->id]);
    }

    public function test_it_lists_albums_with_pagination(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        Album::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/albums')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_updates_an_album(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/albums/{$album->id}", ['name' => 'Renamed Album'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Album');
    }

    public function test_it_deletes_an_album(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/albums/{$album->id}")
            ->assertOk();

        $this->assertSoftDeleted('albums', ['id' => $album->id]);
    }

    public function test_name_is_required(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/albums', [])
            ->assertStatus(422);
    }
}

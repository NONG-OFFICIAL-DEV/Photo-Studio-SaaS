<?php

namespace Tests\Feature\Album;

use App\Enums\TenantRole;
use App\Models\Album;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AlbumLifecycleTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_moves_through_the_full_lifecycle(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/ready")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertNotNull($album->fresh()->delivered_at);
    }

    public function test_cannot_mark_ready_before_starting(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/ready")
            ->assertStatus(422);
    }

    public function test_it_can_be_archived_and_not_archived_twice(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->actingAsUser($owner)
            ->postJson("/api/v1/albums/{$album->id}/archive")
            ->assertStatus(422);
    }

    public function test_album_status_changes_are_recorded_in_activity_log(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)->postJson("/api/v1/albums/{$album->id}/start");

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $album->id,
            'log_name' => 'album',
            'tenant_id' => $tenant->id,
        ]);
    }
}

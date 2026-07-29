<?php

namespace Tests\Feature\Album;

use App\Enums\TenantRole;
use App\Models\Album;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AlbumPermissionAndIsolationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_editor_can_update_but_not_delete_an_album(): void
    {
        [$tenant, $editor] = $this->createTenantWithUser(TenantRole::Editor);
        $album = Album::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($editor)
            ->putJson("/api/v1/albums/{$album->id}", ['name' => 'Updated by editor'])
            ->assertOk();

        $this->actingAsUser($editor)
            ->deleteJson("/api/v1/albums/{$album->id}")
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_an_album(): void
    {
        [, $viewer] = $this->createTenantWithUser(TenantRole::Viewer);

        $this->actingAsUser($viewer)
            ->postJson('/api/v1/albums', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_view_another_tenants_album(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $albumB = Album::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/albums/{$albumB->id}")
            ->assertNotFound();
    }

    public function test_a_tenants_album_list_never_includes_another_tenants_albums(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);

        Album::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Album::factory()->count(4)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->getJson('/api/v1/albums')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}

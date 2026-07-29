<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_create_a_service(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)->postJson('/api/v1/services', [
            'name' => 'Silver Wedding Package',
            'price' => 500,
            'pricing_unit' => 'fixed',
            'duration_minutes' => 180,
            'deliverables' => '50 edited photos, 1 album',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Silver Wedding Package')
            ->assertJsonPath('data.price', 500)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('services', ['name' => 'Silver Wedding Package', 'tenant_id' => $owner->tenant_id]);
    }

    public function test_creating_a_service_requires_name_price_and_valid_pricing_unit(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/services', ['price' => 100, 'pricing_unit' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.name.0', 'The name field is required.');

        $this->assertArrayHasKey('pricing_unit', $response->json('meta.errors'));
    }

    public function test_price_must_be_non_negative(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/services', ['name' => 'Bad Price', 'price' => -10, 'pricing_unit' => 'fixed'])
            ->assertStatus(422);

        $this->assertArrayHasKey('price', $response->json('meta.errors'));
    }

    public function test_owner_can_view_update_and_delete_a_service(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Original Package']);

        $this->actingAsUser($owner)
            ->getJson("/api/v1/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Original Package');

        $this->actingAsUser($owner)
            ->putJson("/api/v1/services/{$service->id}", ['name' => 'Updated Package', 'price' => 750])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Package')
            ->assertJsonPath('data.price', 750);

        $this->actingAsUser($owner)
            ->deleteJson("/api/v1/services/{$service->id}")
            ->assertOk();

        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_creating_a_service_with_a_category_attaches_it(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $category = $this->actingAsUser($owner)
            ->postJson('/api/v1/services/categories', ['name' => 'Portrait Sessions'])
            ->json('data');

        $response = $this->actingAsUser($owner)->postJson('/api/v1/services', [
            'category_id' => $category['id'],
            'name' => 'Solo Portrait',
            'price' => 80,
            'pricing_unit' => 'per_hour',
        ]);

        $response->assertCreated()->assertJsonPath('data.category.name', 'Portrait Sessions');
    }
}

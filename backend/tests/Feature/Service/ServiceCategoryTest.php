<?php

namespace Tests\Feature\Service;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class ServiceCategoryTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_lists_updates_and_deletes_categories(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $category = $this->postJson('/api/v1/services/categories', ['name' => 'Wedding Packages'])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/services/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Wedding Packages']);

        $this->putJson("/api/v1/services/categories/{$category['id']}", ['name' => 'Premium Wedding Packages'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Premium Wedding Packages');

        $this->deleteJson("/api/v1/services/categories/{$category['id']}")
            ->assertOk();

        $this->assertSoftDeleted('service_categories', ['id' => $category['id']]);
    }

    public function test_category_names_are_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/services/categories', ['name' => 'Portrait'])->assertCreated();

        $this->postJson('/api/v1/services/categories', ['name' => 'Portrait'])
            ->assertStatus(422);
    }

    public function test_two_tenants_can_each_have_a_category_with_the_same_name(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/services/categories', ['name' => 'Portrait'])
            ->assertCreated();

        $this->actingAsUser($ownerB)
            ->postJson('/api/v1/services/categories', ['name' => 'Portrait'])
            ->assertCreated();
    }
}

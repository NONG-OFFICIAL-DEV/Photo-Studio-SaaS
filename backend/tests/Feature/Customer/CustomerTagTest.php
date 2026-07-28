<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerTagTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_it_creates_lists_updates_and_deletes_tags(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $tag = $this->postJson('/api/v1/customers/tags', ['name' => 'VIP', 'color' => '#123456'])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/customers/tags')
            ->assertOk()
            ->assertJsonFragment(['name' => 'VIP']);

        $this->putJson("/api/v1/customers/tags/{$tag['id']}", ['name' => 'Super VIP'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Super VIP');

        $this->deleteJson("/api/v1/customers/tags/{$tag['id']}")
            ->assertOk();

        $this->assertSoftDeleted('customer_tags', ['id' => $tag['id']]);
    }

    public function test_tag_names_are_unique_per_tenant(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner);

        $this->postJson('/api/v1/customers/tags', ['name' => 'VIP'])->assertCreated();

        $this->postJson('/api/v1/customers/tags', ['name' => 'VIP'])
            ->assertStatus(422);
    }

    public function test_two_tenants_can_each_have_a_tag_with_the_same_name(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->postJson('/api/v1/customers/tags', ['name' => 'VIP'])
            ->assertCreated();

        $this->actingAsUser($ownerB)
            ->postJson('/api/v1/customers/tags', ['name' => 'VIP'])
            ->assertCreated();
    }
}

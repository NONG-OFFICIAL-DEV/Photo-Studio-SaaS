<?php

namespace Tests\Feature\Customer;

use App\Enums\TenantRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_create_a_customer(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->withHeaders($this->authHeader($owner))->postJson('/api/v1/customers', [
            'name' => 'Sok Pisey',
            'email' => 'pisey@example.test',
            'phone' => '012345678',
            'gender' => 'female',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Sok Pisey')
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.is_blacklisted', false);

        $this->assertDatabaseHas('customers', ['email' => 'pisey@example.test', 'tenant_id' => $owner->tenant_id]);
    }

    public function test_creating_a_customer_requires_a_name(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->withHeaders($this->authHeader($owner))
            ->postJson('/api/v1/customers', ['email' => 'noname@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.name.0', 'The name field is required.');
    }

    public function test_owner_can_view_update_and_delete_a_customer(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Original Name']);

        $this->withHeaders($this->authHeader($owner))
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Original Name');

        $this->withHeaders($this->authHeader($owner))
            ->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->withHeaders($this->authHeader($owner))
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_creating_a_customer_with_tags_attaches_them(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $tag = $this->withHeaders($this->authHeader($owner))
            ->postJson('/api/v1/customers/tags', ['name' => 'VIP', 'color' => '#FF0000'])
            ->json('data');

        $response = $this->withHeaders($this->authHeader($owner))->postJson('/api/v1/customers', [
            'name' => 'Tagged Customer',
            'tag_ids' => [$tag['id']],
        ]);

        $response->assertCreated()->assertJsonPath('data.tags.0.name', 'VIP');
    }
}

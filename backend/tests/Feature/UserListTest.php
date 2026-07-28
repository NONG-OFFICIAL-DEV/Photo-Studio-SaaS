<?php

namespace Tests\Feature;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_list_tenant_users(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->addUserToTenant($tenant, TenantRole::Photographer, ['name' => 'Photographer Two']);

        $response = $this->actingAsUser($owner)->getJson('/api/v1/users');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Photographer Two', $names);
        $this->assertCount(2, $names);
    }

    public function test_photographer_without_users_view_cannot_list_users(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_user_list_never_includes_another_tenants_users(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $this->addUserToTenant($tenantB, TenantRole::Photographer, ['name' => 'Tenant B Photographer']);

        $response = $this->actingAsUser($ownerA)->getJson('/api/v1/users');

        $names = collect($response->json('data'))->pluck('name');
        $this->assertNotContains('Tenant B Photographer', $names);
    }
}

<?php

namespace Tests\Feature\User;

use App\Enums\TenantRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class UserDeactivationTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_deactivate_an_employee(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/users/{$photographer->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertEquals(UserStatus::Inactive, $photographer->fresh()->status);
    }

    public function test_a_deactivated_employee_cannot_log_in(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer, [
            'email' => 'deactivated@example.test',
            'password' => Hash::make('Passw0rd123'),
        ]);

        $this->actingAsUser($owner)->postJson("/api/v1/users/{$photographer->id}/deactivate")->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'deactivated@example.test',
            'password' => 'Passw0rd123',
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'ACCOUNT_DEACTIVATED');
    }

    public function test_an_owner_cannot_deactivate_their_own_account(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/users/{$owner->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('code', 'CANNOT_DEACTIVATE_SELF');
    }

    public function test_the_last_active_owner_cannot_be_deactivated_by_another_owner(): void
    {
        [$tenant, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $ownerB = $this->addUserToTenant($tenant, TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->postJson("/api/v1/users/{$ownerB->id}/deactivate")
            ->assertOk();

        // Now ownerA is the only active owner left — deactivating them
        // (even by another still-active user) must be blocked.
        $manager = $this->addUserToTenant($tenant, TenantRole::Manager);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $manager->givePermissionTo('users.delete');

        $this->actingAsUser($manager)
            ->postJson("/api/v1/users/{$ownerA->id}/deactivate")
            ->assertStatus(422)
            ->assertJsonPath('code', 'CANNOT_DEACTIVATE_LAST_OWNER');
    }

    public function test_deactivating_one_of_two_owners_is_allowed(): void
    {
        [$tenant, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $ownerB = $this->addUserToTenant($tenant, TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->postJson("/api/v1/users/{$ownerB->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_owner_can_reactivate_a_deactivated_employee(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer, ['status' => UserStatus::Inactive]);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/users/{$photographer->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertEquals(UserStatus::Active, $photographer->fresh()->status);
    }

    public function test_a_manager_cannot_deactivate_an_employee_by_default(): void
    {
        [$tenant, $manager] = $this->createTenantWithUser(TenantRole::Manager);
        $photographer = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($manager)
            ->postJson("/api/v1/users/{$photographer->id}/deactivate")
            ->assertForbidden();
    }

    public function test_the_staff_list_excludes_inactive_users_by_default(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->addUserToTenant($tenant, TenantRole::Photographer, ['name' => 'Active One']);
        $this->addUserToTenant($tenant, TenantRole::Photographer, ['name' => 'Inactive One', 'status' => UserStatus::Inactive]);

        $response = $this->actingAsUser($owner)->getJson('/api/v1/users')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertContains('Active One', $names);
        $this->assertNotContains('Inactive One', $names);
    }

    public function test_the_staff_list_includes_inactive_users_when_requested(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->addUserToTenant($tenant, TenantRole::Photographer, ['name' => 'Inactive One', 'status' => UserStatus::Inactive]);

        $response = $this->actingAsUser($owner)->getJson('/api/v1/users?include_inactive=1')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertContains('Inactive One', $names);
    }
}

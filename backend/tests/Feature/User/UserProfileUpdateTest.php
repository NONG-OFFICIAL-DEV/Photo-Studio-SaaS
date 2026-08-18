<?php

namespace Tests\Feature\User;

use App\Enums\TenantRole;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class UserProfileUpdateTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_update_an_employees_name_email_phone_and_role(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}/profile", [
                'name' => 'Renamed Employee',
                'email' => 'renamed@example.test',
                'phone' => '012345678',
                'role' => TenantRole::Editor->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Employee')
            ->assertJsonPath('data.email', 'renamed@example.test')
            ->assertJsonPath('data.phone', '012345678')
            ->assertJsonPath('data.roles.0', TenantRole::Editor->value);

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'name' => 'Renamed Employee']);
    }

    public function test_a_partial_update_leaves_other_fields_untouched(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer, [
            'name' => 'Original Name',
            'phone' => '099999999',
        ]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}/profile", ['role' => TenantRole::Editor->value])
            ->assertOk();

        $employee->refresh();
        $this->assertSame('Original Name', $employee->name);
        $this->assertSame('099999999', $employee->phone);
        $this->assertTrue($employee->hasRole(TenantRole::Editor->value));
    }

    public function test_changing_an_employees_email_resets_verification_and_notifies_them(): void
    {
        Notification::fake();

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer, ['email_verified_at' => now()]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}/profile", ['email' => 'new-address@example.test'])
            ->assertOk();

        $employee->refresh();
        $this->assertSame('new-address@example.test', $employee->email);
        $this->assertNull($employee->email_verified_at);

        Notification::assertSentTo($employee, VerifyEmail::class);
    }

    public function test_setting_an_unchanged_email_does_not_reset_verification(): void
    {
        Notification::fake();

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer, [
            'email' => 'same@example.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}/profile", ['email' => 'same@example.test', 'name' => 'Still Verified'])
            ->assertOk();

        $this->assertNotNull($employee->refresh()->email_verified_at);
        Notification::assertNotSentTo($employee, VerifyEmail::class);
    }

    public function test_the_owner_role_cannot_be_assigned_via_this_endpoint(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $employee = $this->addUserToTenant($tenant, TenantRole::Photographer);

        $this->actingAsUser($owner)
            ->putJson("/api/v1/users/{$employee->id}/profile", ['role' => TenantRole::Owner->value])
            ->assertStatus(422);
    }

    public function test_changing_the_sole_active_owners_role_is_rejected(): void
    {
        [$tenant, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $ownerB = $this->addUserToTenant($tenant, TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->postJson("/api/v1/users/{$ownerB->id}/deactivate")
            ->assertOk();

        // ownerA is now the sole active owner — changing their role away
        // from Owner must be blocked, same guard as deactivate().
        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/users/{$ownerA->id}/profile", ['role' => TenantRole::Manager->value])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CANNOT_CHANGE_LAST_OWNERS_ROLE');

        $this->assertTrue($ownerA->fresh()->hasRole(TenantRole::Owner->value));
    }

    public function test_changing_one_of_two_active_owners_role_is_allowed(): void
    {
        [$tenant, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        $ownerB = $this->addUserToTenant($tenant, TenantRole::Owner);

        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/users/{$ownerB->id}/profile", ['role' => TenantRole::Manager->value])
            ->assertOk();

        $this->assertTrue($ownerB->fresh()->hasRole(TenantRole::Manager->value));
    }

    public function test_a_photographer_cannot_update_another_employees_profile(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $other = $this->addUserToTenant($tenant, TenantRole::Editor);

        $this->actingAsUser($photographer)
            ->putJson("/api/v1/users/{$other->id}/profile", ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_an_owner_cannot_update_a_user_from_another_tenant(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $employeeB = $this->addUserToTenant($tenantB, TenantRole::Photographer);

        $this->actingAsUser($ownerA)
            ->putJson("/api/v1/users/{$employeeB->id}/profile", ['name' => 'New Name'])
            ->assertNotFound();
    }
}

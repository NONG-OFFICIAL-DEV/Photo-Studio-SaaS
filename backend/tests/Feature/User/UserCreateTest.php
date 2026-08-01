<?php

namespace Tests\Feature\User;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class UserCreateTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_create_an_employee_with_a_role(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $response = $this->actingAsUser($owner)
            ->postJson('/api/v1/users', [
                'name' => 'New Photographer',
                'email' => 'photographer@example.com',
                'password' => 'password123',
                'role' => TenantRole::Photographer->value,
            ])
            ->assertCreated();

        $response->assertJsonPath('data.name', 'New Photographer');
        $this->assertDatabaseHas('users', ['email' => 'photographer@example.com']);
    }

    public function test_a_user_without_users_create_cannot_create_an_employee(): void
    {
        [, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);

        $this->actingAsUser($photographer)
            ->postJson('/api/v1/users', [
                'name' => 'New Employee',
                'email' => 'nope@example.com',
                'password' => 'password123',
                'role' => TenantRole::Viewer->value,
            ])
            ->assertForbidden();
    }

    public function test_an_owner_with_an_unverified_email_cannot_create_an_employee(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner, ['email_verified_at' => null]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/users', [
                'name' => 'New Photographer',
                'email' => 'photographer@example.com',
                'password' => 'password123',
                'role' => TenantRole::Photographer->value,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_an_employee_cannot_be_created_with_the_owner_role(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/users', [
                'name' => 'Co Owner',
                'email' => 'coowner@example.com',
                'password' => 'password123',
                'role' => TenantRole::Owner->value,
            ])
            ->assertStatus(422);
    }

    public function test_creating_an_employee_is_blocked_once_the_plans_max_users_limit_is_reached(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['max_users' => 1]);

        // The owner itself already counts as 1 of 1.
        $this->actingAsUser($owner)
            ->postJson('/api/v1/users', [
                'name' => 'One Too Many',
                'email' => 'toomany@example.com',
                'password' => 'password123',
                'role' => TenantRole::Viewer->value,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'toomany@example.com']);
    }

    public function test_an_employee_can_be_created_when_under_the_max_users_limit(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->activeSubscription->plan->update(['max_users' => 5]);

        $this->actingAsUser($owner)
            ->postJson('/api/v1/users', [
                'name' => 'Room To Grow',
                'email' => 'room@example.com',
                'password' => 'password123',
                'role' => TenantRole::Viewer->value,
            ])
            ->assertCreated();
    }

    public function test_plan_limits_are_reachable_by_any_tenant_role(): void
    {
        [$tenant, ] = $this->createTenantWithUser(TenantRole::Owner);
        $viewer = $this->addUserToTenant($tenant, TenantRole::Viewer);
        $tenant->activeSubscription->plan->update(['max_users' => 5, 'monthly_order_limit' => 20]);

        $response = $this->actingAsUser($viewer)->getJson('/api/v1/plan-limits')->assertOk();

        $response->assertJsonPath('data.max_users', 5);
        $response->assertJsonPath('data.users_count', 2);
        $response->assertJsonPath('data.monthly_order_limit', 20);
        $response->assertJsonPath('data.orders_this_month_count', 0);
    }
}

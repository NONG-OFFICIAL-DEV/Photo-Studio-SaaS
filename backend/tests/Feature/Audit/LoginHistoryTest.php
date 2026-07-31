<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Login history VIEWING is platform-admin-only (see config/permissions.php
 * — 'audit.view' isn't in the catalog, so no tenant role can ever hold it).
 * Recording still happens for every login attempt; these tests verify that
 * via the admin surface (App\Http\Controllers\Api\V1\Admin\
 * AdminAuditController), which is where a super admin investigates it.
 */
class LoginHistoryTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_no_tenant_role_can_view_login_history(): void
    {
        foreach (TenantRole::cases() as $role) {
            [, $user] = $this->createTenantWithUser($role);

            $this->actingAsUser($user)
                ->getJson('/api/v1/audit/login-history')
                ->assertForbidden();
        }
    }

    public function test_a_successful_login_is_recorded(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/login-history?tenant_id={$tenant->id}")
            ->assertOk();

        $entries = collect($response->json('data'));
        $success = $entries->firstWhere('properties.email', $owner->email);

        $this->assertNotNull($success);
        $this->assertTrue($success['properties']['success']);
        $this->assertSame($owner->id, $success['causer']['id']);
    }

    public function test_a_failed_login_with_wrong_password_is_recorded(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/login-history?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);

        $this->assertNotNull($entry);
        $this->assertFalse($entry['properties']['success']);
        $this->assertSame('Invalid credentials', $entry['properties']['reason']);
    }
}

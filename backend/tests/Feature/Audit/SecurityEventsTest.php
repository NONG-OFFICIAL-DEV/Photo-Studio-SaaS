<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

/**
 * Security events VIEWING is platform-admin-only (see config/permissions.php
 * — 'audit.view' isn't in the catalog, so no tenant role, including the
 * tenant Owner, can ever hold it). Recording still happens for every
 * tenant; these tests verify that via the admin surface (App\Http\
 * Controllers\Api\V1\Admin\AdminAuditController), which is where a super
 * admin investigates it.
 */
class SecurityEventsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_no_tenant_role_can_view_security_events(): void
    {
        foreach (TenantRole::cases() as $role) {
            [, $user] = $this->createTenantWithUser($role);

            $this->actingAsUser($user)
                ->getJson('/api/v1/audit/security-events')
                ->assertForbidden();
        }
    }

    public function test_a_failed_login_appears_in_security_events(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/security-events?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['properties']['success']);
    }

    public function test_a_successful_login_does_not_appear_in_security_events(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/security-events?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);
        $this->assertNull($entry);
    }

    public function test_a_permission_denied_event_is_recorded_and_visible_to_admin(): void
    {
        $superAdmin = $this->superAdmin();
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $cashier = $this->addUserToTenant($tenant, TenantRole::Cashier);

        $this->actingAsUser($cashier)->getJson('/api/v1/users')->assertForbidden();

        $response = $this->actingAsUser($superAdmin)
            ->getJson("/api/v1/admin/audit/security-events?tenant_id={$tenant->id}")
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame($cashier->id, $entry['causer']['id']);
        $this->assertSame('GET', $entry['properties']['method']);
        $this->assertSame('/api/v1/users', $entry['properties']['path']);
    }
}

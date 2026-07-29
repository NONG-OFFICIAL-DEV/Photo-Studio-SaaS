<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class SecurityEventsTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_a_failed_login_appears_in_security_events(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/security-events')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);
        $this->assertNotNull($entry);
        $this->assertFalse($entry['properties']['success']);
    }

    public function test_a_successful_login_does_not_appear_in_security_events(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/security-events')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);
        $this->assertNull($entry);
    }

    public function test_a_permission_denied_event_within_the_same_tenant_is_visible_to_its_owner(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $cashier = $this->addUserToTenant($tenant, TenantRole::Cashier);

        $this->actingAsUser($cashier)->getJson('/api/v1/users')->assertForbidden();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/security-events')
            ->assertOk();

        $entry = collect($response->json('data'))->first();
        $this->assertNotNull($entry);
        $this->assertSame($cashier->id, $entry['causer']['id']);
        $this->assertSame('GET', $entry['properties']['method']);
        $this->assertSame('/api/v1/users', $entry['properties']['path']);
    }
}

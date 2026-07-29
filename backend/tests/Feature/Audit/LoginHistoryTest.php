<?php

namespace Tests\Feature\Audit;

use App\Enums\TenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_a_successful_login_is_recorded(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertOk();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/login-history')
            ->assertOk();

        $entries = collect($response->json('data'));
        $success = $entries->firstWhere('properties.email', $owner->email);

        $this->assertNotNull($success);
        $this->assertTrue($success['properties']['success']);
        $this->assertSame($owner->id, $success['causer']['id']);
    }

    public function test_a_failed_login_with_wrong_password_is_recorded(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();

        $response = $this->actingAsUser($owner)
            ->getJson('/api/v1/audit/login-history')
            ->assertOk();

        $entry = collect($response->json('data'))->firstWhere('properties.email', $owner->email);

        $this->assertNotNull($entry);
        $this->assertFalse($entry['properties']['success']);
        $this->assertSame('Invalid credentials', $entry['properties']['reason']);
    }

    public function test_login_history_is_isolated_per_tenant(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);
        [, $ownerB] = $this->createTenantWithUser(TenantRole::Owner, ['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/login', ['email' => $ownerA->email, 'password' => 'password'])->assertOk();

        $response = $this->actingAsUser($ownerB)
            ->getJson('/api/v1/audit/login-history')
            ->assertOk();

        $emails = collect($response->json('data'))->pluck('properties.email');
        $this->assertFalse($emails->contains($ownerA->email));
    }

    public function test_a_cashier_cannot_view_login_history(): void
    {
        [, $cashier] = $this->createTenantWithUser(TenantRole::Cashier);

        $this->actingAsUser($cashier)
            ->getJson('/api/v1/audit/login-history')
            ->assertForbidden();
    }
}

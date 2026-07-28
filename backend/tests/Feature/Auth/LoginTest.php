<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.test',
            'password' => Hash::make('Passw0rd123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.test',
            'password' => 'Passw0rd123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'user@example.test');

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user2@example.test',
            'password' => Hash::make('Passw0rd123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user2@example.test',
            'password' => 'WrongPassword',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.test',
            'password' => 'whatever123',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Passw0rd123'),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Passw0rd123',
        ])->json('data.access_token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}

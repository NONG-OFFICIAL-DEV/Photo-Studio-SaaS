<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function authHeader(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_a_user_can_update_their_own_email(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'old@example.test',
            'password' => Hash::make('Passw0rd123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/email', ['current_password' => 'Passw0rd123', 'email' => 'new@example.test']);

        $response->assertOk()->assertJsonPath('data.email', 'new@example.test');

        $user->refresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_updating_email_requires_the_correct_current_password(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Passw0rd123'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/email', ['current_password' => 'WrongPassword1', 'email' => 'new@example.test'])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.current_password.0', 'The current password is incorrect.');

        $this->assertNotSame('new@example.test', $user->fresh()->email);
    }

    public function test_the_new_email_must_be_unique(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'taken@example.test']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Passw0rd123'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/email', ['current_password' => 'Passw0rd123', 'email' => 'taken@example.test'])
            ->assertStatus(422);
    }

    public function test_a_user_keeping_their_own_current_email_is_not_rejected_as_a_duplicate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'same@example.test',
            'password' => Hash::make('Passw0rd123'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/email', ['current_password' => 'Passw0rd123', 'email' => 'same@example.test'])
            ->assertOk();
    }

    public function test_a_user_can_update_their_own_password(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'user@example.test',
            'password' => Hash::make('OldPassw0rd1'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'OldPassw0rd1',
                'password' => 'NewPassw0rd1',
                'password_confirmation' => 'NewPassw0rd1',
            ])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.test', 'password' => 'OldPassw0rd1'])
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.test', 'password' => 'NewPassw0rd1'])
            ->assertOk();
    }

    public function test_updating_password_requires_the_correct_current_password(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('OldPassw0rd1'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'WrongPassword1',
                'password' => 'NewPassw0rd1',
                'password_confirmation' => 'NewPassw0rd1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.current_password.0', 'The current password is incorrect.');
    }

    public function test_the_new_password_must_meet_complexity_requirements(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('OldPassw0rd1'),
        ]);

        $this->withHeaders($this->authHeader($user))
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'OldPassw0rd1',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertStatus(422);
    }

    public function test_updating_email_or_password_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/email', ['current_password' => 'x', 'email' => 'new@example.test'])
            ->assertUnauthorized();

        $this->putJson('/api/v1/auth/password', ['current_password' => 'x', 'password' => 'NewPassw0rd1', 'password_confirmation' => 'NewPassw0rd1'])
            ->assertUnauthorized();
    }
}

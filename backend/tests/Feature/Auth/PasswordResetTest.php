<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_reset_link_to_a_known_email(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'reset@example.test']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'reset@example.test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_email_points_to_the_frontend_spa(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'reset2@example.test']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'reset2@example.test']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $url = $mail->actionUrl;

            return str_contains($url, config('app.frontend_url').'/reset-password')
                && str_contains($url, 'email=reset2%40example.test');
        });
    }

    public function test_it_resets_the_password_with_a_valid_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'reset3@example.test']);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => 'reset3@example.test',
            'password' => 'NewPassw0rd1',
            'password_confirmation' => 'NewPassw0rd1',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset3@example.test',
            'password' => 'NewPassw0rd1',
        ])->assertOk();
    }

    public function test_it_rejects_an_invalid_reset_token(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'reset4@example.test']);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'not-a-real-token',
            'email' => 'reset4@example.test',
            'password' => 'NewPassw0rd1',
            'password_confirmation' => 'NewPassw0rd1',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }
}

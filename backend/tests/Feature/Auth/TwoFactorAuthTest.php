<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'tenant_id' => null,
            'password' => Hash::make('Passw0rd123'),
        ]);
    }

    protected function tenantUser(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Passw0rd123'),
        ]);
    }

    protected function currentOtp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    protected function bearer(User $user): array
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Passw0rd123',
        ])->json('data.access_token');

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_a_super_admin_can_enroll_in_two_factor(): void
    {
        $admin = $this->superAdmin();

        $setup = $this->withHeaders($this->bearer($admin))
            ->postJson('/api/v1/auth/two-factor/setup')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($setup['secret']);
        $this->assertStringContainsString('otpauth://totp/', $setup['otpauth_url']);
        $this->assertStringContainsString('<svg', $setup['qr_code_svg']);

        $confirm = $this->withHeaders($this->bearer($admin))
            ->postJson('/api/v1/auth/two-factor/confirm', ['code' => $this->currentOtp($setup['secret'])])
            ->assertOk();

        $this->assertCount(8, $confirm->json('data.recovery_codes'));
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
    }

    public function test_confirming_with_the_wrong_code_does_not_enable_two_factor(): void
    {
        $admin = $this->superAdmin();

        $setup = $this->withHeaders($this->bearer($admin))
            ->postJson('/api/v1/auth/two-factor/setup')
            ->json('data');

        $this->withHeaders($this->bearer($admin))
            ->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TWO_FACTOR_CODE');

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }

    public function test_a_regular_tenant_user_cannot_enroll_in_two_factor(): void
    {
        $user = $this->tenantUser();

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/auth/two-factor/setup')
            ->assertForbidden();
    }

    public function test_login_requires_a_code_once_two_factor_is_enabled(): void
    {
        $admin = $this->superAdmin();
        $twoFactor = app(TwoFactorAuthService::class);
        $setup = $twoFactor->generateSecret($admin);
        $twoFactor->confirm($admin->fresh(), $this->currentOtp($setup['secret']));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->assertOk();

        $login->assertJsonPath('data.requires_two_factor', true);
        $this->assertNotEmpty($login->json('data.two_factor_token'));
        $this->assertArrayNotHasKey('access_token', $login->json('data'));
    }

    public function test_a_correct_code_completes_the_login(): void
    {
        $admin = $this->superAdmin();
        $twoFactor = app(TwoFactorAuthService::class);
        $setup = $twoFactor->generateSecret($admin);
        $twoFactor->confirm($admin->fresh(), $this->currentOtp($setup['secret']));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->json('data');

        $verify = $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $login['two_factor_token'],
            'code' => $this->currentOtp($setup['secret']),
        ])->assertOk();

        $this->assertNotEmpty($verify->json('data.access_token'));
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_a_recovery_code_completes_the_login_and_is_single_use(): void
    {
        $admin = $this->superAdmin();
        $twoFactor = app(TwoFactorAuthService::class);
        $setup = $twoFactor->generateSecret($admin);
        $recoveryCodes = $twoFactor->confirm($admin->fresh(), $this->currentOtp($setup['secret']));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->json('data');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $login['two_factor_token'],
            'code' => $recoveryCodes[0],
        ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);

        // A second login attempt using the same already-consumed recovery code must fail.
        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->json('data');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $secondLogin['two_factor_token'],
            'code' => $recoveryCodes[0],
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_TWO_FACTOR_CODE');
    }

    public function test_an_incorrect_code_is_rejected_and_the_challenge_can_be_retried(): void
    {
        $admin = $this->superAdmin();
        $twoFactor = app(TwoFactorAuthService::class);
        $setup = $twoFactor->generateSecret($admin);
        $twoFactor->confirm($admin->fresh(), $this->currentOtp($setup['secret']));

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->json('data');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $login['two_factor_token'],
            'code' => '000000',
        ])->assertStatus(401)->assertJsonPath('code', 'INVALID_TWO_FACTOR_CODE');

        // The challenge token should still be usable for a retry.
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => $login['two_factor_token'],
            'code' => $this->currentOtp($setup['secret']),
        ])->assertOk();
    }

    public function test_an_expired_or_unknown_challenge_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'two_factor_token' => 'not-a-real-token',
            'code' => '123456',
        ])->assertStatus(401)->assertJsonPath('code', 'TWO_FACTOR_CHALLENGE_EXPIRED');
    }

    public function test_a_super_admin_can_disable_two_factor_with_their_password(): void
    {
        $admin = $this->superAdmin();
        $headers = $this->bearer($admin);

        $setup = $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/setup')->json('data');
        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/two-factor/confirm', ['code' => $this->currentOtp($setup['secret'])])
            ->assertOk();

        // The JWT guard caches whichever user it first resolves for the
        // lifetime of the guard instance (see JWTGuard::user()) — since
        // this test process reuses the same container across requests,
        // that cache would otherwise still reflect pre-2FA state here.
        // A real request never hits this, since each one gets a fresh
        // guard; forgetting it forces a real re-resolution from the DB.
        auth()->forgetGuards();

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/two-factor/disable', ['current_password' => 'WrongPassword'])
            ->assertStatus(422)
            ->assertJsonPath('meta.errors.current_password.0', 'The current password is incorrect.');

        auth()->forgetGuards();

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/two-factor/disable', ['current_password' => 'Passw0rd123'])
            ->assertOk();

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);

        // Logging in no longer requires a code.
        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Passw0rd123',
        ])->assertJsonPath('data.requires_two_factor', null);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserOauthProvider;
use App\Services\Google\GoogleIdTokenVerifierInterface;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGoogleIdTokenVerifier;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, PlanSeeder::class]);

        FakeGoogleIdTokenVerifier::$claims = [];
        $this->app->bind(GoogleIdTokenVerifierInterface::class, fn () => new FakeGoogleIdTokenVerifier);
    }

    protected function fakeGoogleToken(string $token, string $sub, string $email, bool $verified = true, ?string $name = 'Google User'): void
    {
        FakeGoogleIdTokenVerifier::$claims[$token] = [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $verified,
            'name' => $name,
        ];
    }

    public function test_a_new_google_account_with_no_local_match_must_register_first(): void
    {
        $this->fakeGoogleToken('tok-new', 'google-sub-1', 'new@example.test');

        $this->postJson('/api/v1/auth/google', ['id_token' => 'tok-new'])
            ->assertOk()
            ->assertJsonPath('data.requires_registration', true);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_google_register_creates_a_tenant_user_and_oauth_link(): void
    {
        $this->fakeGoogleToken('tok-new', 'google-sub-1', 'new@example.test', name: 'New Owner');

        $response = $this->postJson('/api/v1/auth/google/register', [
            'id_token' => 'tok-new',
            'studio_name' => 'Google Studio',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'new@example.test')
            ->assertJsonPath('data.user.roles.0', 'owner')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);

        $tenant = Tenant::where('name', 'Google Studio')->firstOrFail();
        $user = User::withoutGlobalScopes()->where('email', 'new@example.test')->firstOrFail();

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame('New Owner', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('user_oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-1',
        ]);
    }

    public function test_a_returning_google_user_logs_in_directly(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        UserOauthProvider::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-sub-2']);

        $this->fakeGoogleToken('tok-returning', 'google-sub-2', $user->email);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'tok-returning'])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
    }

    public function test_matching_a_locally_verified_email_auto_links_and_logs_in(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'verified@example.test']);
        $this->assertNotNull($user->email_verified_at);

        $this->fakeGoogleToken('tok-link', 'google-sub-3', 'verified@example.test');

        $this->postJson('/api/v1/auth/google', ['id_token' => 'tok-link'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'verified@example.test');

        $this->assertDatabaseHas('user_oauth_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-sub-3',
        ]);
    }

    public function test_matching_a_locally_unverified_email_requires_login_first(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'email' => 'unverified@example.test']);

        $this->fakeGoogleToken('tok-unverified-local', 'google-sub-4', 'unverified@example.test');

        $this->postJson('/api/v1/auth/google', ['id_token' => 'tok-unverified-local'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'REQUIRES_LOGIN_TO_LINK');

        $this->assertDatabaseMissing('user_oauth_providers', ['user_id' => $user->id]);
    }

    public function test_an_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleToken('tok-unverified-google', 'google-sub-5', 'nope@example.test', verified: false);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'tok-unverified-google'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_GOOGLE_TOKEN');
    }

    public function test_an_unrecognized_id_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => 'not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_GOOGLE_TOKEN');
    }
}

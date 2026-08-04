<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_signed_link_verifies_the_email_and_redirects_to_frontend(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=success');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        ).'&tampered=1';

        $this->get($url)->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**
     * Reproduces the production bug: the link is generated as https:// (see
     * AppServiceProvider's URL::forceScheme('https')), but Cloudflare
     * terminates TLS and forwards to the origin over plain HTTP — without
     * `trustProxies` configured (see bootstrap/app.php), Laravel computed
     * the expected signature using that raw http:// connection instead of
     * the forwarded https://, so every real verification link failed with
     * "Invalid signature." even though nothing was actually tampered with.
     */
    public function test_a_signed_link_is_still_valid_behind_a_reverse_proxy_that_forwards_https(): void
    {
        URL::forceScheme('https');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute(
            'api.v1.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $forwardedUrl = str_replace('https://', 'http://', $url);

        $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get($forwardedUrl);

        $response->assertRedirect(config('app.frontend_url').'/email-verified?status=success');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_authenticated_user_can_request_resend(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);

        $token = auth('api')->login($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}

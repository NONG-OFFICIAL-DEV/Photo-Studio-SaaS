<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, PlanSeeder::class]);
    }

    public function test_it_registers_a_new_tenant_with_owner_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'studio_name' => 'Golden Light Studio',
            'owner_name' => 'Sok Dara',
            'email' => 'owner@goldenlight.test',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
            'phone' => '+855123456789',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'owner@goldenlight.test')
            ->assertJsonPath('data.user.roles.0', 'owner')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('tenants', ['name' => 'Golden Light Studio']);

        $tenant = Tenant::where('name', 'Golden Light Studio')->firstOrFail();
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'trial',
        ]);

        $user = User::withoutGlobalScopes()->where('email', 'owner@goldenlight.test')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('owner'));
        $this->assertTrue($user->can('tenant.billing.manage'));
    }

    public function test_registration_uses_the_requested_plan_trial_days(): void
    {
        $plan = Plan::where('code', 'starter')->firstOrFail();
        $plan->update(['trial_days' => 7]);

        $this->postJson('/api/v1/auth/register', [
            'studio_name' => 'Another Studio',
            'owner_name' => 'Owner Two',
            'email' => 'owner2@example.test',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
            'plan_code' => 'starter',
        ])->assertCreated();

        $tenant = Tenant::where('name', 'Another Studio')->firstOrFail();
        $subscription = $tenant->subscriptions()->firstOrFail();

        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertTrue($subscription->trial_ends_at->diffInDays(now()) <= 7);
    }

    public function test_it_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.test', 'tenant_id' => null]);

        $this->postJson('/api/v1/auth/register', [
            'studio_name' => 'Studio X',
            'owner_name' => 'Owner X',
            'email' => 'taken@example.test',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('meta.errors.email.0', 'The email has already been taken.');
    }

    public function test_it_rejects_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'studio_name' => 'Studio Y',
            'owner_name' => 'Owner Y',
            'email' => 'ownery@example.test',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Different123',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('meta.errors.password.0', 'The password field confirmation does not match.');
    }
}

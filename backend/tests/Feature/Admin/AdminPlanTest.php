<?php

namespace Tests\Feature\Admin;

use App\Enums\PlanFeatureValueType;
use App\Enums\TenantRole;
use App\Models\Plan;
use App\Models\PlanFeatureListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class AdminPlanTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_it_lists_plans(): void
    {
        $superAdmin = $this->superAdmin();
        Plan::factory()->count(2)->create();

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/plans')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_creates_a_plan(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Pro',
                'code' => 'pro',
                'price_monthly' => 49,
                'max_users' => 10,
                'trial_days' => 14,
                'has_reports' => true,
            ])
            ->assertCreated();

        $response->assertJsonPath('data.code', 'pro')
            ->assertJsonPath('data.price_monthly', 49)
            ->assertJsonPath('data.has_reports', true);

        $this->assertDatabaseHas('plans', ['code' => 'pro']);
    }

    public function test_it_creates_a_plan_with_catalog_backed_feature_values(): void
    {
        $superAdmin = $this->superAdmin();
        $textFeature = PlanFeatureListing::factory()->create(['value_type' => PlanFeatureValueType::Text]);
        $boolFeature = PlanFeatureListing::factory()->create(['value_type' => PlanFeatureValueType::Boolean]);

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Pro',
                'code' => 'pro',
                'price_monthly' => 49,
                'feature_labels' => [
                    $textFeature->key => ['en' => 'Up to 20', 'km' => 'រហូតដល់ 20'],
                    $boolFeature->key => true,
                ],
            ])
            ->assertCreated();

        $response->assertJsonPath("data.feature_labels.{$textFeature->key}.en", 'Up to 20')
            ->assertJsonPath("data.feature_labels.{$boolFeature->key}", true);

        $plan = Plan::where('code', 'pro')->firstOrFail();
        $this->assertSame('Up to 20', $plan->feature_labels[$textFeature->key]['en']);
        $this->assertTrue($plan->feature_labels[$boolFeature->key]);
    }

    public function test_a_text_feature_missing_the_english_value_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        $textFeature = PlanFeatureListing::factory()->create(['value_type' => PlanFeatureValueType::Text]);

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Pro',
                'code' => 'pro',
                'price_monthly' => 49,
                'feature_labels' => [$textFeature->key => ['km' => 'តម្លៃ']], // missing en
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey("feature_labels.{$textFeature->key}.en", $response->json('meta.errors'));
        $this->assertDatabaseMissing('plans', ['code' => 'pro']);
    }

    public function test_a_boolean_feature_given_a_non_boolean_value_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();
        $boolFeature = PlanFeatureListing::factory()->create(['value_type' => PlanFeatureValueType::Boolean]);

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Pro',
                'code' => 'pro',
                'price_monthly' => 49,
                'feature_labels' => [$boolFeature->key => 'yes'],
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey("feature_labels.{$boolFeature->key}", $response->json('meta.errors'));
        $this->assertDatabaseMissing('plans', ['code' => 'pro']);
    }

    public function test_an_unknown_feature_key_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Pro',
                'code' => 'pro',
                'price_monthly' => 49,
                'feature_labels' => ['not_a_real_key' => true],
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('feature_labels.not_a_real_key', $response->json('meta.errors'));
        $this->assertDatabaseMissing('plans', ['code' => 'pro']);
    }

    public function test_plan_code_must_be_unique(): void
    {
        $superAdmin = $this->superAdmin();
        Plan::factory()->create(['code' => 'pro']);

        $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plans', ['name' => 'Pro 2', 'code' => 'pro'])
            ->assertStatus(422);
    }

    public function test_it_updates_a_plan(): void
    {
        $superAdmin = $this->superAdmin();
        $plan = Plan::factory()->create(['name' => 'Old Name']);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/plans/{$plan->id}", ['name' => 'New Name', 'price_monthly' => 99])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.price_monthly', 99);
    }

    public function test_it_deletes_a_plan_with_no_subscriptions(): void
    {
        $superAdmin = $this->superAdmin();
        $plan = Plan::factory()->create();

        $this->actingAsUser($superAdmin)
            ->deleteJson("/api/v1/admin/plans/{$plan->id}")
            ->assertOk();

        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_it_refuses_to_delete_a_plan_with_active_subscriptions(): void
    {
        $superAdmin = $this->superAdmin();
        [, , $plan] = $this->createTenantOnPlan();

        $this->actingAsUser($superAdmin)
            ->deleteJson("/api/v1/admin/plans/{$plan->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_a_regular_tenant_user_cannot_manage_plans(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/admin/plans')
            ->assertForbidden();
    }

    /**
     * createTenantWithUser() already creates its own throwaway "test_plan"
     * subscription — reuse that same fixture path so the returned plan is
     * the one actually referenced by a real Subscription row.
     */
    protected function createTenantOnPlan(): array
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $plan = Plan::where('code', 'test_plan')->firstOrFail();

        return [$tenant, $owner, $plan];
    }
}

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

class AdminPlanFeatureListingTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    protected function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
    }

    public function test_it_lists_feature_listings(): void
    {
        $superAdmin = $this->superAdmin();
        // The create_plan_feature_listings_table migration seeds ~7
        // built-in rows, present in every test run — assert against the
        // count *before* this test's own rows, not a hardcoded total.
        $before = PlanFeatureListing::count();
        PlanFeatureListing::factory()->count(2)->create();

        $this->actingAsUser($superAdmin)
            ->getJson('/api/v1/admin/plan-feature-listings')
            ->assertOk()
            ->assertJsonCount($before + 2, 'data');
    }

    public function test_it_creates_a_feature_listing(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plan-feature-listings', [
                'key' => 'custom_reports',
                'label_en' => 'Reports',
                'label_km' => 'របាយការណ៍',
                'value_type' => 'boolean',
                'sort_order' => 1,
            ])
            ->assertCreated();

        $response->assertJsonPath('data.key', 'custom_reports')
            ->assertJsonPath('data.label.en', 'Reports')
            ->assertJsonPath('data.value_type', 'boolean');

        $this->assertDatabaseHas('plan_feature_listings', ['key' => 'custom_reports', 'value_type' => 'boolean']);
    }

    public function test_key_must_be_unique(): void
    {
        $superAdmin = $this->superAdmin();
        $existing = PlanFeatureListing::factory()->create();

        $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plan-feature-listings', [
                'key' => $existing->key,
                'label_en' => 'Reports Again',
                'value_type' => 'boolean',
            ])
            ->assertStatus(422);
    }

    public function test_value_type_must_be_a_known_enum_value(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAsUser($superAdmin)
            ->postJson('/api/v1/admin/plan-feature-listings', [
                'key' => 'custom_reports',
                'label_en' => 'Reports',
                'value_type' => 'number',
            ])
            ->assertStatus(422);
    }

    public function test_it_updates_a_feature_listing(): void
    {
        $superAdmin = $this->superAdmin();
        $listing = PlanFeatureListing::factory()->create(['label_en' => 'Old Label']);

        $this->actingAsUser($superAdmin)
            ->putJson("/api/v1/admin/plan-feature-listings/{$listing->id}", ['label_en' => 'New Label'])
            ->assertOk()
            ->assertJsonPath('data.label.en', 'New Label');
    }

    public function test_it_deletes_a_feature_listing_even_when_a_plan_still_references_it(): void
    {
        $superAdmin = $this->superAdmin();
        $listing = PlanFeatureListing::factory()->create(['value_type' => PlanFeatureValueType::Boolean]);
        Plan::factory()->create(['feature_labels' => [$listing->key => true]]);

        $this->actingAsUser($superAdmin)
            ->deleteJson("/api/v1/admin/plan-feature-listings/{$listing->id}")
            ->assertOk();

        $this->assertSoftDeleted('plan_feature_listings', ['id' => $listing->id]);
    }

    public function test_a_regular_tenant_user_cannot_manage_feature_listings(): void
    {
        [, $owner] = $this->createTenantWithUser(TenantRole::Owner);

        $this->actingAsUser($owner)
            ->getJson('/api/v1/admin/plan-feature-listings')
            ->assertForbidden();
    }
}

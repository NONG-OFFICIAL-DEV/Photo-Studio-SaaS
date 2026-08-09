<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_active_plans_without_authentication(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'sort_order' => 2, 'is_active' => true]);
        Plan::factory()->create(['name' => 'Enterprise', 'sort_order' => 1, 'is_active' => true]);
        Plan::factory()->create(['name' => 'Retired Plan', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Ordered by sort_order, and the inactive plan never appears.
        $this->assertSame('Enterprise', $response->json('data.0.name'));
        $this->assertSame('Starter', $response->json('data.1.name'));
    }

    public function test_it_exposes_pricing_and_feature_fields(): void
    {
        Plan::factory()->create([
            'name' => 'Professional',
            'price_monthly' => 15,
            'has_reports' => true,
        ]);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Professional')
            ->assertJsonPath('data.0.price_monthly', 15)
            ->assertJsonPath('data.0.has_reports', true);
    }
}

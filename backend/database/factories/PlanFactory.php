<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    protected static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            'name' => 'Plan '.self::$sequence,
            'code' => 'plan_'.self::$sequence,
            'description' => fake()->sentence(),
            'price_monthly' => 29,
            'price_quarterly' => 79,
            'price_yearly' => 290,
            'max_users' => 5,
            'storage_limit_gb' => 10,
            'monthly_order_limit' => 100,
            'has_watermark_gallery' => true,
            'has_online_gallery' => true,
            'has_reports' => false,
            'has_api_access' => false,
            'trial_days' => 14,
            'is_active' => true,
            'sort_order' => self::$sequence,
        ];
    }
}

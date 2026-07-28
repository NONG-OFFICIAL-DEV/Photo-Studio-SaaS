<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free_trial',
                'name' => 'Free Trial',
                'description' => '14-day full-feature trial, no credit card required.',
                'price_monthly' => 0,
                'price_quarterly' => null,
                'price_yearly' => null,
                'max_users' => 2,
                'storage_limit_gb' => 1,
                'monthly_order_limit' => 10,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => false,
                'has_api_access' => false,
                'trial_days' => 14,
                'sort_order' => 0,
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For solo photographers and small studios getting started.',
                'price_monthly' => 19,
                'price_quarterly' => 51.30,
                'price_yearly' => 182.40,
                'max_users' => 3,
                'storage_limit_gb' => 10,
                'monthly_order_limit' => 50,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => false,
                'has_api_access' => false,
                'trial_days' => 0,
                'sort_order' => 1,
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing studios that need reporting and more storage.',
                'price_monthly' => 49,
                'price_quarterly' => 132.30,
                'price_yearly' => 470.40,
                'max_users' => 10,
                'storage_limit_gb' => 100,
                'monthly_order_limit' => 300,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => true,
                'has_api_access' => false,
                'trial_days' => 0,
                'sort_order' => 2,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large multi-branch studios with unlimited scale and API access.',
                'price_monthly' => 149,
                'price_quarterly' => 402.30,
                'price_yearly' => 1430.40,
                'max_users' => null,
                'storage_limit_gb' => null,
                'monthly_order_limit' => null,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => true,
                'has_api_access' => true,
                'trial_days' => 0,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

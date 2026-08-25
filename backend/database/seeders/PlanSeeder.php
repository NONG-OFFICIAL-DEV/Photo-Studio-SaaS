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
                'feature_labels' => [
                    'max_users' => ['en' => 'Up to 2 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 2'],
                    'storage_limit_gb' => ['en' => '1 GB storage', 'km' => 'ទំហំផ្ទុក 1 GB'],
                    'monthly_order_limit' => ['en' => '10 orders / month', 'km' => 'ការកម្មង់ 10 / ខែ'],
                    'has_online_gallery' => ['en' => 'Online client galleries', 'km' => 'វិចិត្រសាលអតិថិជនអនឡាញ'],
                    'has_watermark_gallery' => ['en' => 'Watermarked online gallery', 'km' => 'វិចិត្រសាលមានស្លាកទឹក'],
                ],
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For solo photographers and small studios getting started.',
                'price_monthly' => 5,
                'price_quarterly' => 13.50,
                'price_yearly' => 48.00,
                'max_users' => 3,
                'storage_limit_gb' => 10,
                'monthly_order_limit' => 50,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => false,
                'has_api_access' => false,
                'trial_days' => 0,
                'sort_order' => 1,
                'feature_labels' => [
                    'max_users' => ['en' => 'Up to 3 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 3'],
                    'storage_limit_gb' => ['en' => '10 GB storage', 'km' => 'ទំហំផ្ទុក 10 GB'],
                    'monthly_order_limit' => ['en' => '50 orders / month', 'km' => 'ការកម្មង់ 50 / ខែ'],
                    'has_online_gallery' => ['en' => 'Online client galleries', 'km' => 'វិចិត្រសាលអតិថិជនអនឡាញ'],
                    'has_watermark_gallery' => ['en' => 'Watermarked online gallery', 'km' => 'វិចិត្រសាលមានស្លាកទឹក'],
                ],
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing studios that need reporting and more storage.',
                'price_monthly' => 15,
                'price_quarterly' => 40.50,
                'price_yearly' => 144.00,
                'max_users' => 10,
                'storage_limit_gb' => 100,
                'monthly_order_limit' => 300,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => true,
                'has_api_access' => false,
                'trial_days' => 0,
                'sort_order' => 2,
                'feature_labels' => [
                    'max_users' => ['en' => 'Up to 10 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 10'],
                    'storage_limit_gb' => ['en' => '100 GB storage', 'km' => 'ទំហំផ្ទុក 100 GB'],
                    'monthly_order_limit' => ['en' => '300 orders / month', 'km' => 'ការកម្មង់ 300 / ខែ'],
                    'has_online_gallery' => ['en' => 'Online client galleries', 'km' => 'វិចិត្រសាលអតិថិជនអនឡាញ'],
                    'has_reports' => ['en' => 'Reporting & analytics', 'km' => 'របាយការណ៍ និងវិភាគទិន្នន័យ'],
                    'has_watermark_gallery' => ['en' => 'Watermarked online gallery', 'km' => 'វិចិត្រសាលមានស្លាកទឹក'],
                ],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For large multi-branch studios with unlimited scale and API access.',
                'price_monthly' => 39,
                'price_quarterly' => 105.30,
                'price_yearly' => 374.40,
                'max_users' => null,
                'storage_limit_gb' => null,
                'monthly_order_limit' => null,
                'has_watermark_gallery' => true,
                'has_online_gallery' => true,
                'has_reports' => true,
                'has_api_access' => true,
                'trial_days' => 0,
                'sort_order' => 3,
                'feature_labels' => [
                    'max_users' => ['en' => 'Unlimited users', 'km' => 'អ្នកប្រើប្រាស់គ្មានកំណត់'],
                    'storage_limit_gb' => ['en' => 'Unlimited storage', 'km' => 'ទំហំផ្ទុកគ្មានកំណត់'],
                    'monthly_order_limit' => ['en' => 'Unlimited orders', 'km' => 'ការកម្មង់គ្មានកំណត់'],
                    'has_online_gallery' => ['en' => 'Online client galleries', 'km' => 'វិចិត្រសាលអតិថិជនអនឡាញ'],
                    'has_reports' => ['en' => 'Reporting & analytics', 'km' => 'របាយការណ៍ និងវិភាគទិន្នន័យ'],
                    'has_api_access' => ['en' => 'API access', 'km' => 'ការចូលប្រើ API'],
                    'has_watermark_gallery' => ['en' => 'Watermarked online gallery', 'km' => 'វិចិត្រសាលមានស្លាកទឹក'],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

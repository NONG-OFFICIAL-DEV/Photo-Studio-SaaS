<?php

namespace Database\Seeders;

use App\Enums\PlanFeatureValueType;
use App\Models\Plan;
use App\Models\PlanFeatureListing;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent and self-sufficient — doesn't rely on the catalog rows
        // the create_plan_feature_listings_table migration seeds, so this
        // seeder still works standalone (`db:seed --class=PlanSeeder`) on a
        // database where that migration's one-time seed was later edited
        // or removed by an admin.
        $catalog = [
            ['key' => 'users', 'label_en' => 'Users', 'label_km' => 'អ្នកប្រើប្រាស់', 'value_type' => PlanFeatureValueType::Text, 'sort_order' => 0],
            ['key' => 'storage', 'label_en' => 'Storage', 'label_km' => 'ទំហំផ្ទុក', 'value_type' => PlanFeatureValueType::Text, 'sort_order' => 1],
            ['key' => 'orders', 'label_en' => 'Monthly Orders', 'label_km' => 'ការកម្មង់ប្រចាំខែ', 'value_type' => PlanFeatureValueType::Text, 'sort_order' => 2],
            ['key' => 'online_gallery', 'label_en' => 'Online Client Galleries', 'label_km' => 'វិចិត្រសាលអតិថិជនអនឡាញ', 'value_type' => PlanFeatureValueType::Boolean, 'sort_order' => 3],
            ['key' => 'reports', 'label_en' => 'Reports & Analytics', 'label_km' => 'របាយការណ៍ និងវិភាគទិន្នន័យ', 'value_type' => PlanFeatureValueType::Boolean, 'sort_order' => 4],
            ['key' => 'telegram', 'label_en' => 'Telegram Notifications', 'label_km' => 'ការជូនដំណឹង Telegram', 'value_type' => PlanFeatureValueType::Boolean, 'sort_order' => 5],
            ['key' => 'api_access', 'label_en' => 'API Access', 'label_km' => 'ការចូលប្រើ API', 'value_type' => PlanFeatureValueType::Boolean, 'sort_order' => 6],
        ];

        foreach ($catalog as $row) {
            PlanFeatureListing::updateOrCreate(['key' => $row['key']], [...$row, 'is_active' => true]);
        }

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
                    'users' => ['en' => 'Up to 2 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 2'],
                    'storage' => ['en' => '1 GB storage', 'km' => 'ទំហំផ្ទុក 1 GB'],
                    'orders' => ['en' => '10 orders / month', 'km' => 'ការកម្មង់ 10 / ខែ'],
                    'online_gallery' => true,
                    'reports' => false,
                    'telegram' => false,
                    'api_access' => false,
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
                    'users' => ['en' => 'Up to 3 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 3'],
                    'storage' => ['en' => '10 GB storage', 'km' => 'ទំហំផ្ទុក 10 GB'],
                    'orders' => ['en' => '50 orders / month', 'km' => 'ការកម្មង់ 50 / ខែ'],
                    'online_gallery' => true,
                    'reports' => false,
                    'telegram' => false,
                    'api_access' => false,
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
                    'users' => ['en' => 'Up to 10 users', 'km' => 'អ្នកប្រើប្រាស់រហូតដល់ 10'],
                    'storage' => ['en' => '100 GB storage', 'km' => 'ទំហំផ្ទុក 100 GB'],
                    'orders' => ['en' => '300 orders / month', 'km' => 'ការកម្មង់ 300 / ខែ'],
                    'online_gallery' => true,
                    'reports' => true,
                    'telegram' => false,
                    'api_access' => false,
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
                    'users' => ['en' => 'Unlimited users', 'km' => 'អ្នកប្រើប្រាស់គ្មានកំណត់'],
                    'storage' => ['en' => 'Unlimited storage', 'km' => 'ទំហំផ្ទុកគ្មានកំណត់'],
                    'orders' => ['en' => 'Unlimited orders', 'km' => 'ការកម្មង់គ្មានកំណត់'],
                    'online_gallery' => true,
                    'reports' => true,
                    'telegram' => false,
                    'api_access' => true,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], [...$plan, 'is_active' => true]);
        }
    }
}

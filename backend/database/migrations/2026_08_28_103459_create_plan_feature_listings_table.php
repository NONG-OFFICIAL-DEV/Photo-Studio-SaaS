<?php

use App\Enums\PlanFeatureValueType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catalog of marketing feature rows shown on the public pricing page —
 * display copy only, never read by entitlement/gating logic (see
 * App\Http\Middleware\EnsurePlanFeature for the real gate, which checks
 * Plan's own has_* / max_* columns directly). Every Plan.feature_labels
 * value is keyed by this catalog's `key` column going forward:
 * {"reports": true, "users": {"en": "Up to 20", "km": null}} — boolean
 * type stores a real bool, text type stores {en, km}.
 *
 * Seeds the initial catalog and backfills every existing plan's
 * feature_labels from its current raw columns via the query builder (not
 * Eloquent), so this one-time data transform stays frozen even if Plan's
 * own casts/fillable change later. down() only drops the table — the
 * data backfill is intentionally not reversed (standard for a one-time
 * data reshape).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_feature_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('label_en');
            $table->string('label_km')->nullable();
            $table->string('value_type')->default(PlanFeatureValueType::Text->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $catalog = [
            ['key' => 'users', 'label_en' => 'Users', 'label_km' => 'អ្នកប្រើប្រាស់', 'value_type' => PlanFeatureValueType::Text->value, 'sort_order' => 0],
            ['key' => 'storage', 'label_en' => 'Storage', 'label_km' => 'ទំហំផ្ទុក', 'value_type' => PlanFeatureValueType::Text->value, 'sort_order' => 1],
            ['key' => 'orders', 'label_en' => 'Monthly Orders', 'label_km' => 'ការកម្មង់ប្រចាំខែ', 'value_type' => PlanFeatureValueType::Text->value, 'sort_order' => 2],
            ['key' => 'online_gallery', 'label_en' => 'Online Client Galleries', 'label_km' => 'វិចិត្រសាលអតិថិជនអនឡាញ', 'value_type' => PlanFeatureValueType::Boolean->value, 'sort_order' => 3],
            ['key' => 'reports', 'label_en' => 'Reports & Analytics', 'label_km' => 'របាយការណ៍ និងវិភាគទិន្នន័យ', 'value_type' => PlanFeatureValueType::Boolean->value, 'sort_order' => 4],
            ['key' => 'telegram', 'label_en' => 'Telegram Notifications', 'label_km' => 'ការជូនដំណឹង Telegram', 'value_type' => PlanFeatureValueType::Boolean->value, 'sort_order' => 5],
            ['key' => 'api_access', 'label_en' => 'API Access', 'label_km' => 'ការចូលប្រើ API', 'value_type' => PlanFeatureValueType::Boolean->value, 'sort_order' => 6],
        ];

        $now = now();
        DB::table('plan_feature_listings')->insert(array_map(fn (array $row) => [
            'id' => (string) Str::uuid(),
            ...$row,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $catalog));

        foreach (DB::table('plans')->get() as $plan) {
            $featureLabels = [
                'users' => ['en' => $plan->max_users ? "Up to {$plan->max_users}" : 'Unlimited'],
                'storage' => ['en' => $plan->storage_limit_gb ? "{$plan->storage_limit_gb} GB" : 'Unlimited'],
                'orders' => ['en' => $plan->monthly_order_limit ? "Up to {$plan->monthly_order_limit} / month" : 'Unlimited'],
                'online_gallery' => (bool) $plan->has_online_gallery,
                'reports' => (bool) $plan->has_reports,
                'telegram' => (bool) $plan->has_telegram,
                'api_access' => (bool) $plan->has_api_access,
            ];

            DB::table('plans')->where('id', $plan->id)->update([
                'feature_labels' => json_encode($featureLabels),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_feature_listings');
    }
};

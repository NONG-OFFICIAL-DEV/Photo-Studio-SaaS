<?php

namespace Database\Factories;

use App\Enums\PlanFeatureValueType;
use App\Models\PlanFeatureListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeatureListing>
 */
class PlanFeatureListingFactory extends Factory
{
    protected $model = PlanFeatureListing::class;

    protected static int $sequence = 0;

    public function definition(): array
    {
        self::$sequence++;

        return [
            'key' => 'feature_'.self::$sequence,
            'label_en' => 'Feature '.self::$sequence,
            'label_km' => null,
            'value_type' => PlanFeatureValueType::Text,
            'sort_order' => self::$sequence,
            'is_active' => true,
        ];
    }
}

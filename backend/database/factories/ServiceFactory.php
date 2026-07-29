<?php

namespace Database\Factories;

use App\Enums\PricingUnit;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'category_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'deliverables' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 20, 2000),
            'pricing_unit' => PricingUnit::Fixed->value,
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

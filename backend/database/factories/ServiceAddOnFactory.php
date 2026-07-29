<?php

namespace Database\Factories;

use App\Models\ServiceAddOn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceAddOn>
 */
class ServiceAddOnFactory extends Factory
{
    protected $model = ServiceAddOn::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 200),
            'is_active' => true,
        ];
    }
}

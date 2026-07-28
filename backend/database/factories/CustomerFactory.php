<?php

namespace Database\Factories;

use App\Enums\CustomerGender;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birthday' => fake()->date(),
            'gender' => fake()->randomElement(CustomerGender::cases())->value,
            'is_favorite' => false,
            'is_blacklisted' => false,
        ];
    }

    public function favorite(): static
    {
        return $this->state(['is_favorite' => true]);
    }

    public function blacklisted(): static
    {
        return $this->state(['is_blacklisted' => true, 'blacklist_reason' => fake()->sentence()]);
    }
}
